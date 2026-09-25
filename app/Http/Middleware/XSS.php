<?php

namespace App\Http\Middleware;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class XSS
{
    use \RachidLaasri\LaravelInstaller\Helpers\MigrationsHelper;
    public function handle($request, Closure $next)
    {
        $validationResult = $this->validateRequestFiles($request);
        if ($validationResult !== null) {
            return redirect()->back()->with('error', __("File type not supported."));
        }

        if (Auth::check()) {
            \App::setLocale('tr');
            if (Auth::user()->type == 'super admin') {
                $directoryMigrations             = $this->getMigrations();
                $databaseMigrations           = $this->getExecutedMigrations();
                $total = count($directoryMigrations) - count($databaseMigrations);
                if ($total > 0) {
                    return redirect()->route('LaravelUpdater::welcome');
                }
            }
        }
        $data = $request->all();
        array_walk_recursive(
            $data,
            function (&$data) {
                // $data = strip_tags($data);
            }
        );
        $request->merge($data);
        return $next($request);
    }

    private function validateFileExtension($file)
    {
        $supportExtension = $file->getClientOriginalExtension();
        $supportedExtensions = ['jpg', 'jpeg', 'png', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv','gif','webp','avif','rtf','odt','mp3','mp4','m4a','mpeg','ogg','zip','gz','tar'];
        return in_array(strtolower($supportExtension), $supportedExtensions);
    }

    private function validateRequestFiles($request)
    {
        foreach ($request->all() as $inputKey => $inputValue) {
            if ($request->hasFile($inputKey)) {
                $attachments = $request->file($inputKey);

                if (is_array($attachments)) {
                    foreach ($attachments as $attachment) {
                        if ($attachment && !$this->validateFileExtension($attachment)) {
                            return redirect()->back()->with('error', __("File type not supported."));
                        }
                    }
                } else {
                    if ($attachments && !$this->validateFileExtension($attachments)) {
                        return redirect()->back()->with('error', __("File type not supported."));
                    }
                }
            }

            if (is_array($inputValue)) {
                if ($this->checkFilesInArray($inputValue, $inputKey, $request) === false) {
                    return redirect()->back()->with('error', __("File type not supported."));
                }
            }
        }
        return null;
    }

    private function checkFilesInArray($array, $parentKey, $request)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if ($this->checkFilesInArray($value, "$parentKey.$key", $request) === false) {
                    return false;
                }
            } elseif ($value instanceof \Illuminate\Http\UploadedFile) {
                if ($request->hasFile("$parentKey.$key")) {
                    $attachments = $request->file("$parentKey.$key");

                    if (is_array($attachments)) {
                        foreach ($attachments as $attachment) {
                            if ($attachment && !$this->validateFileExtension($attachment)) {
                                return false;
                            }
                        }
                    } else {
                        if ($attachments && !$this->validateFileExtension($attachments)) {
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }
}
