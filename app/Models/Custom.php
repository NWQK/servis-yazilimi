<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Custom extends Model
{



    public static function languages()
    {
        return ['tr'];
    }


    public static function setCommon(array $data)
    {
        $envData = app()->environmentFilePath();
        $envString = file_get_contents($envData);
        if (count($data) > 0) {
            foreach ($data as $key => $val) {
                $keyPosition = strpos($envString, "{$key}=");
                $endLinePosition = strpos($envString, "\n", $keyPosition);
                $oldPosition = substr($envString, $keyPosition, $endLinePosition - $keyPosition);
                if (!$keyPosition || !$endLinePosition || !$oldPosition) {
                    $envString .= "{$key}='{$val}'\n";
                } else {
                    $envString = str_replace($oldPosition, "{$key}='{$val}'", $envString);
                }
            }
        }
        $envString = substr($envString, 0, -1);
        $envString .= "\n";
        if (!file_put_contents($envData, $envString)) {
            return false;
        }
        return true;
    }

    public function langKeyword(){
        $langKeyword=[

        ];
    }
}
