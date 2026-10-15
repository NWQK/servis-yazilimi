<?php
namespace App\Services\Recovery;

/** Decode data literals produced by mysqldump. Never execute dump SQL. */
class SqlDumpReader
{
    public function statements(string $path): \Generator
    {
        $fh=fopen($path,'rb');if(!$fh)throw new RecoveryFailure('SQL yedeği okunamıyor.');
        $buffer='';$statement='';$quote=null;$escaped=false;$lineComment=false;$blockComment=false;
        try {
            while(!feof($fh) || $buffer!=='') {
                if(!feof($fh))$buffer.=fread($fh,65536);
                $limit=feof($fh)?strlen($buffer):max(0,strlen($buffer)-2);$i=0;
                for(;$i<$limit;$i++) {
                    $c=$buffer[$i];$next=$buffer[$i+1]??'';$third=$buffer[$i+2]??'';
                    if($lineComment){if($c==="\n"){$lineComment=false;$statement.="\n";}continue;}
                    if($blockComment){if($c==='*'&&$next==='/'){$blockComment=false;$i++;$statement.=' ';}continue;}
                    if($quote!==null) {
                        $statement.=$c;
                        if($escaped){$escaped=false;continue;}
                        if($c==='\\'&&$quote!=='`'){$escaped=true;continue;}
                        if($c===$quote){if($next===$quote){$statement.=$next;$i++;}else $quote=null;}
                    } else {
                        if($c==='/'&&$next==='*'){$blockComment=true;$i++;continue;}
                        if($c==='#'||($c==='-'&&$next==='-'&&($third===''||ctype_space($third)))){$lineComment=true;if($c==='-')$i++;continue;}
                        if(in_array($c,["'",'"','`'],true))$quote=$c;
                        if($c===';') {if(trim($statement)!=='')yield trim($statement);$statement='';continue;}
                        $statement.=$c;
                    }
                    if(strlen($statement)>67108864)throw new RecoveryFailure('SQL yedeğinde desteklenen sınırı aşan bir ifade var.');
                }
                $buffer=substr($buffer,$i);
            }
            if($quote!==null||$blockComment||trim($statement)!=='')throw new RecoveryFailure('SQL yedeği eksik veya desteklenmeyen biçimde.');
        } finally {fclose($fh);}
    }

    public function rows(string $values): \Generator
    {
        $p=0;$length=strlen($values);
        while($p<$length) {
            while($p<$length&&ctype_space($values[$p]))$p++;
            if($p===$length)return;
            if($values[$p++]!=='(')throw new RecoveryFailure('SQL satır biçimi desteklenmiyor.');
            $row=[];
            while(true) {
                while($p<$length&&ctype_space($values[$p]))$p++;
                if(($values[$p]??'')==="'") {
                    $p++;$value='';$closed=false;
                    while($p<$length) {
                        $c=$values[$p++];
                        if($c==='\\') {
                            if($p===$length)break;$e=$values[$p++];
                            $value.=match($e){'0'=>"\0",'n'=>"\n",'r'=>"\r",'t'=>"\t",'b'=>"\x08",'Z'=>"\x1a",default=>$e};
                        } elseif($c==="'") {
                            if(($values[$p]??'')==="'"){$value.="'";$p++;}else{$closed=true;break;}
                        } else $value.=$c;
                    }
                    if(!$closed)throw new RecoveryFailure('SQL metin değeri tamamlanmamış.');
                } else {
                    $start=$p;while($p<$length&&$values[$p]!==','&&$values[$p]!==')')$p++;
                    $token=trim(substr($values,$start,$p-$start));
                    if(strtoupper($token)==='NULL')$value=null;
                    elseif(preg_match('/\A0x([a-f0-9]*)\z/i',$token,$m)&&strlen($m[1])%2===0)$value=hex2bin($m[1]);
                    elseif(preg_match('/\A[-+]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][-+]?\d+)?\z/',$token))$value=$token;
                    else throw new RecoveryFailure('SQL yedeği çalıştırılabilir veya desteklenmeyen bir değer içeriyor.');
                }
                $row[]=$value;while($p<$length&&ctype_space($values[$p]))$p++;
                $delimiter=$values[$p++]??'';
                if($delimiter===')')break;
                if($delimiter!==',')throw new RecoveryFailure('SQL sütun ayırıcıları geçersiz.');
            }
            yield $row;
            while($p<$length&&ctype_space($values[$p]))$p++;
            if($p<$length){
                if($values[$p++]!==',')throw new RecoveryFailure('SQL satır sonu geçersiz.');
                while($p<$length&&ctype_space($values[$p]))$p++;
                if($p===$length)throw new RecoveryFailure('SQL satır listesi tamamlanmamış.');
            }
        }
    }
}
