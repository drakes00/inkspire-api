<?php
namespace App\Service;

use phpDocumentor\Reflection\Types\False_;
use phpDocumentor\Reflection\Types\String_;
use Reflection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaService
{
    private string $message;

    public function __construct(private HttpClientInterface $client, private FileService $fileService)
    {
    }

    /**
     * This function is used to call the ollama server on Cardiff. It takes the message and send it to the server. The server will return a response.
     * @param string $message
     * @return string
     */
    // public function callOllama(string $message)
    // {
    //     $rep = $this->client->request(
    //         'POST', 
    //         'http://192.168.128.44:11434/api/generate',
    //         [
    //             'headers' => [
    //                 'Content-Type' => 'application/json'
    //             ],
    //             'json' => [
    //                 'model' => 'llama3.1:8b-instruct-fp16',
    //                 'prompt' => $this->message,
    //                 'stream'=> false
    //             ]
    //         ]
    //     );
    //     return json_decode($rep->getContent(),associative:true)["response"];
    // }

    /**
     * This function is used to call the ollama server on Cardiff but with a 0 temperature. It takes the message and send it to the server. The server will return a response.
     * @param string $message
     * @return string
     */
    // public function callOllamaContext(string $message)
    // {
    //     $rep = $this->client->request(
    //         'POST', 
    //         'http://192.168.128.44:11434/api/generate',
    //         [
    //             'headers' => [
    //                 'Content-Type' => 'application/json'
    //             ],
    //             'json' => [
    //                 'model' => 'llama3.1:8b-instruct-fp16',
    //                 'prompt' => $this->message,
    //                 'stream'=> false,
    //                 'option'=>[
    //                     'temperature'=>0
    //                 ]
    //             ]
    //         ]
    //     );
    //     return json_decode($rep->getContent(),associative:true)["response"];
    // }

    /**
     * This function is used to call the local ollama with a 0 temperature. It takes the message and process it.
     * @param string $message
     * @return JSON with all params needed
     */
    public function callOllamaContext(string $message)
    {
        $rep = $this->client->request(
            'POST',
            'http://localhost:11434/api/generate',
            [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'model' => 'llama3:latest',
                    'prompt' => $this->message,
                    'stream' => false,
                    'option' => [
                        'temperature' => 0
                    ]
                ]
            ]
        );
        return json_decode($rep->getContent(), associative: true)["response"];
    }

     /**
     * This function is used to call the local ollama. It takes the message and process it. The temperature is set to allow a few creativity
     * @param string $message
     * @return JSON with all params needed
     */
    public function callOllamaCompletion(string $message)
    {
        $rep = $this->client->request(
            'POST',
            'http://localhost:11434/api/generate',
            [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'model' => 'llama3:latest',
                    'prompt' => $this->message,
                    'stream' => false,
                    'option' => [
                        'temperature' => 0,3
                    ]
                ]
            ]
        );
        return json_decode($rep->getContent(), associative: true);
    }

    /**
     * another model is tested => tiny llama, but it doesn't give relevant results
     */
    // public function callOllamaContext(string $message)
    // {
    //     $rep = $this->client->request(
    //         'POST', 
    //         'http://127.0.0.1:11434/api/generate',
    //         [
    //             'headers' => [
    //                 'Content-Type' => 'application/json'
    //             ],
    //             'json' => [
    //                 'model' => 'tinyllama:latest',
    //                 'prompt' => $this->message,
    //                 'stream'=> false,
    //                 'option'=>[
    //                     'temperature'=>0
    //                 ]
    //             ]
    //         ]
    //     );
    //     return json_decode($rep->getContent(),associative:true)["response"];
    // }

    /**
     * another model is tested => tiny llama, but it doesn't give relevant results
     */
    // public function callOllama(string $message)
    // {
    //     $rep = $this->client->request(
    //         'POST', 
    //         'http://localhost:11434/api/generate',
    //         [
    //             'headers' => [
    //                 'Content-Type' => 'application/json'
    //             ],
    //             'json' => [
    //                 'model' => 'tinyllama:latest',
    //                 'prompt' => $this->message,
    //                 'stream'=> false
    //             ]
    //         ]
    //     );
    //     return json_decode($rep->getContent(),associative:true)["response"];
    // }

    /**
     * This function is used to make a synthesis of all generated response when we want to add a sentence. It takes the array of response given and user query to keep a good context and generate a global relevant answer.
     * @param array $responses 
     * @param string $userQuery 
     * @return array of the Ollama responses.
     */
    public function synthesizeAddResponses(array $responses, string $userQuery) : string
    {
        $combinedResponse = implode(" ", $responses);
        $this->message = json_encode([
                "messages" => [
                    "role" => "system",
                    "content" => "You are an expert in phrasing; you receive several completions from a user request and you must synthesize them. Here are the completions: {$combinedResponse}.
    Your will answer with the following JSON format without anything else: 
    '''json
    {
        'generated_text' : (generated answer),
    }    
    '''
    "
                ],
                [
                    "role" => "user",
                    "content" => $userQuery
                ]
            ]);
            $resp = $this->callOllamaCompletion($this->message);
            return $resp;
    }
    
    /**
     * This function is used to rephrase the text provided. It takes the text and the context and returns the rephrased text.
     * @param string $allText
     * @param int $id
     * @return array
     */
    public function rephraseReformulation(string $allText, int $id)
    {
        $context = $this->fileService->getContextFromId($id);
        $answers = [];
        if (isset($allText)) {
            $chunks = $this->splitTextAccordingToSentences($allText);
        }
        foreach ($chunks as $chunk) {
            $resp = $this->rephraseCompletion($chunk, $context);
        }
        $response = $this->concatenateChunks($resp);
        return $response;
    }

    /**
     * This function is used in rephrase and translate options, to manage one or many answers. It allows to concatenate all generated answer accordingly to the threshold's model.
     * @param array 
     * @return array $response array of the manage data generated
     */
    public function concatenateChunks(array $chunks)
    {
        $response = [];
        $temp = [];
        $total_duration = 0;
        $load_duration = 0;
        $total_token = 0;
        $eval_duration = 0;
        $response["response"] = "";
        foreach ($chunks as $chunk) {
            $response["response"] = $response["response"] . " " . $this->parseMessage($chunk["response"]);
            $total_duration = $total_duration +  $chunk["total_duration"];
            $load_duration = $load_duration + $chunk["load_duration"];
            $total_token = $total_token + $chunk["eval_count"];
            $eval_duration = $eval_duration + $chunk["eval_duration"];
        }
        $response["total_duration"] = $total_duration;
        $response["load_duration"] = $load_duration;
        $response["eval_count"] = $total_token;
        $response["tokenpersecond"] = ($total_token / $eval_duration) * 10**9;
        return $response;
    }

    /**
     * This function is used in add option, to manage one or many answers. It calls function to synthetize all generated answer accordingly to the threshold's model.
     * @param array $arrayChunks
     * @param string $userQuery
     * @return array $response array of the manage data generated
     */
    public function manageChunks(array $arraychunks, string $userQuery)
    {
        $response = [];
        $temp = [];
        $total_duration = 0;
        $load_duration = 0;
        $total_token = 0;
        $eval_duration = 0;
        foreach ($arraychunks as $chunk) {
            $temp[]["response"] = $this->parseMessage($chunk["response"]);
            $total_duration = $total_duration +  $chunk["total_duration"];
            $load_duration = $load_duration + $chunk["load_duration"];
            $total_token = $total_token + $chunk["eval_count"];
            $eval_duration = $eval_duration + $chunk["eval_duration"];
        }
        $response["total_duration"] = $total_duration;
        $response["load_duration"] = $load_duration;
        $response["eval_count"] = $total_token;
        $response["tokenpersecond"] = ($total_token / $eval_duration) * 10**9;
        //var_dump($temp);
        if (count($temp) > 1) {
            $synthesizedResponse = $this->synthesizeAddResponses($temp, $userQuery);
            $response["response"] = $synthesizedResponse;
            return $response;
        }
        $response["response"] = $this->parseMessage($arraychunks[0]["response"]);
        return $response;
    }

    /**
     * This function is used to translate the text ($allText) given, using translateCompletion, it retrieves the choosen language in the $userQuery
     * and retrieve the directory context from the file's id. The replacement logic is manage by the concatenateChunks function. 
     * @param string $allText
     * @param string $userQuery
     * @param int $id
     * @return array
     */
    public function translateReformulation(string $allText, string $userQuery, int $id)
    {
        $context = $this->fileService->getContextFromId($id);

        if (isset($allText)) {
            $chunks = $this->splitTextAccordingToSentences($allText);
        }
        foreach ($chunks as $chunk) {
            $resp = $this->translationCompletion($chunk, $context, $userQuery);
        }
        $response = $this->concatenateChunks($resp);
        return $response;
    }

    /**
     * this function is used to add some text, accordingly to the user query. It doesn't replace the text or selected text, it adds the generated text.
     * @param string $allText
     * @param string $userquery
     * @param int $id
     * @return array
     */
    public function addReformulation(string $allText, string $userquery, int $id)
    {
        $context = $this->fileService->getContextFromId($id);

        if (isset($allText)) {
            $chunks = $this->splitTextAccordingToSentences($allText);
        }
        foreach ($chunks as $chunk) {
            $answer[] = $this->addCompletion($chunk, $context, $userquery);
        }
        $answer = $this->manageChunks($answer, $userquery);
        return $answer;
    }


    /**
     * This function function is call when add button is pressed. It takes the directory context, the user query and add some text accordingly to those informations.
     * @param string $text
     * @param string $context
     * @param string $userQuery
     * @return array $resp
     */
    public function addCompletion(string $text, string $context, string $userQuery)
    {
        $resp = [];
        $chunks = $this->splitTextAccordingToSentences($text);
        if (!isset($context) || trim($context) === '') {
            $cont = "You are an expert in expression.";
        } else {
            $cont = $context;
        }
        foreach ($chunks as $chunk) {
            $this->message = json_encode([
                "messages" => [
                    "role" => "system",
                    "content" => "{$cont}. You receive the base text and you have to complete it accordingly to the user query: {$chunk}
        Your will return only a string answering the user query with the following JSON format without anything else:
'''json
{
    \"generated_text\" : (only include the response sentence to the user's request),
}    
'''    
without role system, or any comment, just the answer."
                ],
                [
                    "role" => "user",
                    "content" => $userQuery
                ]
            ]);
            $resp = $this->callOllamaCompletion($this->message);
        }
        return $resp;
    }

    /**
     * This function is used to rephrase the completion of the text. It takes the text and the context and returns the rephrased text.
     * @param string $text
     * @param string $context
     * @return array $resp
     */
    public function rephraseCompletion(string $text, string $context)
    {
        $resp = [];
        $chunks = $this->splitTextAccordingToSentences($text);

        if (!isset($context) || trim($context) === '') {
            $cont = "You are an expert in expression. You receive a text, and your task is to analyze it, and you rephrase all of it ";
        } else {
            $cont = $context;
        }
        foreach ($chunks as $text) {
            $this->message = json_encode([
                "messages" => [
                    "role" => "system",
                    "content" => "{$cont}. And this is the base text you have to analyze and rephrase without making an abstract of it: {$text} 
    Your will return the content of the messages answer with the following JSON format without anything else: 
    '''json
    {
    \"generated_text\" : (generated answer),
    }    
    '''    
without role system, or any comment, just the answer."
                ],
                [
                    "role" => "user",
                    "content" => "Rephrase all the text provided without making an abstract of it, accordingly to the context and return the content of the answer with the following format without anything else:"
                ]
            ]);
            $resp[] = $this->callOllamaCompletion($this->message);
        }
        return $resp;
    }
    
    /**
     * This function is used to rephrase the completion of the text. It takes the text and the context and returns the rephrased text.
     * @param string $text
     * @param string $context
     * @param string userquery
     * @return array $resp
     */
    public function translationCompletion(string $text, string $context, string $userQuery)
    {
        $resp = [];
        $chunks = $this->splitTextAccordingToSentences($text);

        if (!isset($context) || trim($context) === '') {
            $cont = "You are an expert in expression and traduction.";
        } else {
            $cont = $context;
        }
        foreach ($chunks as $text) {
            $this->message = json_encode([
                "messages" => [
                    "role" => "system",
                    "content" => "{$cont}. This is the base text you have to translate accordingly to the language provided by the userquery: {$text} 
    Your will return only the translation of the text, answer with the following JSON format without anything else: 
        '''json
    {
    \"generated_text\" : (generated answer),
    }    
    '''    
without role system, or any comment, just the answer."
                ],
                [
                    "role" => "user",
                    "content" => $userQuery
                ]
            ]);
            $resp[] = $this->callOllamaCompletion($this->message);
        }
        return $resp;
    }


    /**
     * Split the text received if it's longer than 8192 characters. It will split it in as many chunks as needed to create an array of chunks.
     * @param string $allText
     * @param int $maxLenght 8192 normally for the llama3:8b
     * @return string[]
     */
    public function splitTextAccordingToSentences(string $allText, int $maxLenght = 800)
    {
        $chunks = [];
        $currentChunk = '';
        $pattern = '/(?<=\.) (?=[A-Z])/';
        $splitByPoint = preg_split($pattern, $allText, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($splitByPoint as $sentence) {
            if (strlen($currentChunk . ' ' . $sentence) > $maxLenght) {
                $chunks[] = trim($currentChunk);
                $currentChunk = $sentence;
            } else {
                $currentChunk .= ' ' . $sentence;

            }
            if (!empty (trim($currentChunk))) {
                $chunks[] = trim($currentChunk);
            }
            return $chunks;
        }
    }

    /**
     * This function is used to call the ollama on local to reformulate the context or modify as the user wants.
     * It takes the previous context if there is one, and returns the new context.
     * @param string $contextfrom
     * @return string $rep
     */
    public function changeDirectoryContext(string $contextfrom)
    {
        $this->message = json_encode([
            "messages" => [
                "role" => "system",
                "content" => "You're an expert in classification, you are able to provide a clear and concise context from user description. Your answer is short and follow this format : 
'''
You're an expert in {topics in the user query}, you are able to generate {object topic in the user query} and reformulate files content accordingly.
''' 
Nothing else but this sentence completed. If user description is null or empty, return 'You are an expert in expression. You receive text and you have to analyze it and reformulate or answer the user query if you need to'. "
            ],
            [
                "role" => "user",
                "content" => $contextfrom
            ]
        ]);
        $rep = $this->callOllamaContext($this->message);
        return $rep;
    }

    /**
     * This function is used to parse the message received from the ollama server. It will remove the code block and return the json decoded message.
     * @param string $respFromOllama
     * @return string
     */
    // public function parseMessage($respFromOllama): string
    // {
    //     $match = explode(":", $respFromOllama);

    //     $match[0] = str_replace("'", "\"", $match[0]);
    //     $match[1] = str_replace("\"", "", $match[1]);

    //     if (str_starts_with($match[1], "'")) {
    //         $match[1] = '"' . substr($match[1], 1, strlen($match[1]) - 3) . '"}';
    //     }
    //     $respFromOllama = $match[0] . ":" . $match[1];
    //     var_dump($respFromOllama);
    //     $mess_tab = json_decode($respFromOllama, true, flags: JSON_THROW_ON_ERROR);

    //     if ($mess_tab === null) {
    //         error_log('Error decoding JSON: ' . json_last_error_msg());
    //         throw new \Exception('Invalid JSON received: ' . json_last_error_msg());
    //     }

    //     if (!isset($mess_tab['generated_text'])) {
    //         throw new \Exception('Missing "generated_text" in the response.');
    //     }

    //     return $mess_tab['generated_text'];
    // }


    /**
     * This function is the last version of the parse we used, because we had a lot of problem with it. As much as we change the code, the output format change too. 
     * @param string $outFromModel
     */
    function parseMessage($outFromModel)
    {
        preg_match('/\{.*?\}/s', $outFromModel, $matches);
        if(isset($matches[0])){
            $outFromModel = $matches[0];
        }
        $outFromModel = str_replace("Here is the response: ", "", $outFromModel);
        $outFromModel = str_replace("json", "", $outFromModel);
        $outFromModel = str_replace("```", "", $outFromModel);
        $outFromModel = str_replace("{\"messages\":{\"generated_text\": \""," \"{ \"generated_text\" : ", $outFromModel);
        $outFromModel = str_replace("''' { \"messages\": { \"role\": \"system\", \"content\": ", "", $outFromModel);
        $outFromModel = str_replace("\"\"\"", "\"", $outFromModel);
        $outFromModel = str_replace(" ''' {\"messages\":{\"", "", $outFromModel);
        $outFromModel = str_replace("'''{\"messages\": {\"role\": \"system\", \"content\":", "", $outFromModel);
        $outFromModel = str_replace("\"{'generated_text': ''' { \"generated_text\" :", " \"{ \"generated_text\" : ", $outFromModel);
        $outFromModel = str_replace("'' {\"messages\":{\"role\":\"system\",\"content\":", "", $outFromModel);
        $outFromModel = str_replace("'generated_text'", "\"generated_text\"", $outFromModel);
        $outFromModel = str_replace(": '", ": \"", $outFromModel);
        $outFromModel = str_replace(".'}", ".\"}", $outFromModel);
        $outFromModel = str_replace("'}", ".\"}", $outFromModel);
        $outFromModel = str_replace("I\'m", "I m", $outFromModel);
        $decoded = json_decode($outFromModel, true);
        if(!isset($decoded['generated_text'])) {
            return "";
        }
        $generatedText = $decoded['generated_text'];
        return $generatedText;
    }
}
?>