<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class InvoicePreviewController extends Controller {
    public function preview(Request $request,int $id,string $action){return response()->json(app(\App\Services\InvoiceChangePreview::class)->build($request,$id,$action))->header('Cache-Control','private, no-store');}
}
