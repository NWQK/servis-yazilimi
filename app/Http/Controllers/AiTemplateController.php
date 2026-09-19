<?php

namespace App\Http\Controllers;

use App\Models\AiTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use OpenAi;

class AiTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create($title)
    {
        $title = AiTemplate::where('content_type', $title)->get();
        return view('ai_template.create', compact('title'));
    }

    public function getTemplateKeywords(Request $request, $id)
    {
        $template = AiTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'template' => '',
                'mode' => 0,
            ]);
        }

        $fieldJson = json_decode($template->field, true); // decode JSON
        $html = '';

        if (!empty($fieldJson) && is_array($fieldJson)) {
            foreach ($fieldJson as $field) {
                $label = $field['label'] ?? '';
                $name = $field['name'] ?? '';
                $placeholder = $field['placeholder'] ?? '';
                $type = $field['type'] ?? 'text';

                $html .= '<div class="form-group col-md-12">';
                $html .= '<label class="form-label">' . e($label) . '</label>';

                if ($type === 'textarea') {
                    $html .= '<textarea rows="3" class="form-control" name="' . e($name) . '" placeholder="' . e($placeholder) . '" required></textarea>';
                } else {
                    $html .= '<input type="text" class="form-control" name="' . e($name) . '" placeholder="' . e($placeholder) . '" required>';
                }

                $html .= '</div>';
            }
        }

        return response()->json([
            'success' => true,
            'active' => $template->is_active,
            'template' => $html,
        ]);
    }

    public function AiPromptGenerate(Request $request)
    {
        // Request type validation
        if (!$request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'Invalid request type']);
        }

        // Load configuration
        $config = settings();
        $secret = $config['openai_secret_key'] ?? null;

        if (empty($secret)) {
            return response()->json([
                'success' => false,
                'message' => __('OpenAI API key is not configured'),
            ]);
        }

        // Fetch template
        $templateId = $request->input('title');
        $aiTemplate = AiTemplate::where('id', $templateId)->first();
        if (!$aiTemplate) {
            return response()->json([
                'success' => false,
                'message' => __('Template not found'),
            ]);
        }

        // Decode template fields
        $templateFields = json_decode($aiTemplate->field, true);
        $inputRules = [];

        foreach ($templateFields ?? [] as $item) {
            if (!empty($item['name'])) {
                $inputRules[$item['name']] = ['required', 'string'];
            }
        }

        $validation = Validator::make($request->all(), $inputRules);
        if ($validation->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('Required fields are missing'),
            ]);
        }

        $finalPrompt = $aiTemplate->template_prompt;

        foreach ($templateFields ?? [] as $item) {

            $placeholder = '##' . $item['name'] . '##';
            $userValue = trim($request->input($item['name']));

            $finalPrompt = str_replace($placeholder, $userValue, $finalPrompt);
        }

        // Communication mode
        $style = $request->input('mode');
        if ($aiTemplate->is_active && !empty($style)) {
            $finalPrompt = str_replace(
                '##communication_style##',
                ucfirst($style),
                $finalPrompt
            );
        } else {
            $finalPrompt = str_replace('##communication_style##', '', $finalPrompt);
        }

        // Language instruction
        $outputLanguage = $request->input('language', 'English');
        $finalPrompt .= "\n\nOutput must be in {$outputLanguage} only.";

        // AI configuration
        $temperature = (float) $request->input('creativity_level', 0.7);
        $tokenLimit = (int) $request->input('maximum_word_limit', 500);
        $variants = (int) $request->input('num_of_outputs', 1);

        try {
            $openAi = OpenAI::client($secret);

            $result = $openAi->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'You generate professional content.'],
                    ['role' => 'user', 'content' => $finalPrompt],
                ],
                'temperature' => $temperature,
                'max_tokens' => $tokenLimit,
                'n' => $variants,
            ]);

            if (empty($result->choices)) {
                return response()->json([
                    'success' => false,
                    'message' => __('AI did not return any content'),
                ]);
            }

            $generatedText = '';
            foreach ($result->choices as $idx => $choice) {
                $content = trim($choice->message->content ?? '');
                $generatedText .= ($variants > 1)
                    ? ($idx + 1) . '. ' . $content . "\n\n"
                    : $content;
            }

            return response()->json([
                'success' => true,
                'content' => trim($generatedText),
            ]);
        } catch (\Throwable $exception) {
            Log::error('AI Generation Failed: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Content generation failed. Please try again.'),
            ]);
        }
    }

}
