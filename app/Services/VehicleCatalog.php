<?php
namespace App\Services;

use App\Models\{User, Vehicle, VehicleType, VehicleBrand, VehicleCatalogImport};
use Illuminate\Support\Facades\DB;

class VehicleCatalog
{
    public const CATEGORIES = ['agir-vasita'=>'Ağır vasıta','otomobil'=>'Otomobil','motosiklet'=>'Motosiklet'];

    public function dataset(string $category): array
    {
        if (!array_key_exists($category, self::CATEGORIES)) { abort(422); }
        return require resource_path('data/vehicle-catalog/'.$category.'.php');
    }

    private function key(string $name): string
    {
        $name=mb_strtolower(str_replace(['İ','ı'],['I','i'],trim($name)), 'UTF-8');
        $name=strtr($name,['š'=>'s','ş'=>'s','ç'=>'c','ë'=>'e','é'=>'e','è'=>'e','ğ'=>'g','ö'=>'o','ü'=>'u','â'=>'a']);
        return preg_replace('/[^\p{L}\p{N}]+/u','',$name);
    }

    public function import(int $ownerId, string $category): array
    {
        $dataset=$this->dataset($category);
        return DB::transaction(function () use ($ownerId,$category,$dataset) {
            User::whereKey($ownerId)->lockForUpdate()->firstOrFail();
            if (VehicleCatalogImport::where('parent_id',$ownerId)->where('category',$category)->where('active',true)->exists()) {
                return ['brands'=>0,'models'=>0,'already_loaded'=>true];
            }
            $brands=VehicleType::where('parent_id',$ownerId)->orderBy('id')->get()->keyBy(fn($brand)=>$this->key($brand->type ?? ''));
            $entries=[]; $addedBrands=0; $addedModels=0;
            foreach ($dataset as $name=>$models) {
                $brand=$brands->get($this->key($name)); $created=!$brand;
                if (!$brand) { $brand=VehicleType::create(['parent_id'=>$ownerId,'type'=>$name]); $addedBrands++; }
                $existing=VehicleBrand::where('parent_id',$ownerId)->where('type',$brand->id)->orderBy('id')->get()->keyBy(fn($model)=>$this->key($model->name ?? ''));
                $entry=['id'=>$brand->id,'name'=>$brand->type,'created'=>$created,'models'=>[]];
                foreach ($models as $modelName) {
                    $model=$existing->get($this->key($modelName)); $modelCreated=!$model;
                    if (!$model) { $model=VehicleBrand::create(['parent_id'=>$ownerId,'type'=>$brand->id,'name'=>$modelName]); $addedModels++; }
                    $entry['models'][]=['id'=>$model->id,'name'=>$model->name,'created'=>$modelCreated];
                }
                $entries[]=$entry;
            }
            VehicleCatalogImport::create(['parent_id'=>$ownerId,'category'=>$category,'active'=>true,'entries'=>$entries]);
            return ['brands'=>$addedBrands,'models'=>$addedModels,'already_loaded'=>false];
        });
    }

    public function undo(int $ownerId, int $importId): array
    {
        return DB::transaction(function () use ($ownerId,$importId) {
            User::whereKey($ownerId)->lockForUpdate()->firstOrFail();
            $batch=VehicleCatalogImport::where('parent_id',$ownerId)->whereKey($importId)->lockForUpdate()->firstOrFail();
            if (!$batch->active) { return ['brands'=>0,'models'=>0,'retained'=>0]; }
            $history=VehicleCatalogImport::where('parent_id',$ownerId)->get();
            $brandOrigins=[]; $modelOrigins=[]; $activeBrands=[]; $activeModels=[];
            foreach ($history as $record) {
                foreach ($record->entries as $entry) {
                    if ($entry['created']) { $brandOrigins[$entry['id']]=$entry; }
                    if ($record->active && $record->id !== $batch->id) { $activeBrands[$entry['id']]=true; }
                    foreach ($entry['models'] as $model) {
                        if ($model['created']) { $modelOrigins[$model['id']]=$model + ['brand_id'=>$entry['id']]; }
                        if ($record->active && $record->id !== $batch->id) { $activeModels[$model['id']]=true; }
                    }
                }
            }
            $deletedBrands=0; $deletedModels=0; $retained=0;
            foreach ($batch->entries as $entry) {
                foreach ($entry['models'] as $snapshot) {
                    $origin=$modelOrigins[$snapshot['id']] ?? null;
                    if (!$origin) { continue; } // A pre-existing manual model must never be deleted.
                    $model=VehicleBrand::where('parent_id',$ownerId)->whereKey($snapshot['id'])->first();
                    if (!$model) { continue; }
                    if (isset($activeModels[$model->id]) || $model->name !== $origin['name'] || (int)$model->type !== (int)$origin['brand_id'] || Vehicle::withTrashed()->where('brand',$model->id)->exists()) { $retained++; continue; }
                    $model->delete(); $deletedModels++;
                }
                $origin=$brandOrigins[$entry['id']] ?? null;
                if (!$origin) { continue; }
                $brand=VehicleType::where('parent_id',$ownerId)->whereKey($entry['id'])->first();
                if (!$brand) { continue; }
                if (isset($activeBrands[$brand->id]) || $brand->type !== $origin['name'] || Vehicle::withTrashed()->where('type',$brand->id)->exists() || VehicleBrand::where('type',$brand->id)->exists()) { $retained++; continue; }
                $brand->delete(); $deletedBrands++;
            }
            $batch->update(['active'=>false]);
            return ['brands'=>$deletedBrands,'models'=>$deletedModels,'retained'=>$retained];
        });
    }
}
