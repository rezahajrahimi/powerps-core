<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ImageDetectText 
{
    protected $api_endpoint;
    protected $headers;

    public function __construct()
    {
        $this->api_endpoint = env('AWS_LAMBDA_FUNCTION');
        $this->setHeaders();
    }

    protected function setHeaders(){
        
        $this->headers = [
            "Content-Type"  => "application/json",
            "Accept"        => "application/json",
        ];

    }

    public function lambda($image_url){

        // Default result array
        $result = ['success'=>false,'body'=>[]];

        // Create params array
        $params = [
            'image_url'   => $image_url,
        ];

        // Send the request
        try {
            
            $response = Http::withHeaders($this->headers)->post($this->api_endpoint,$params);
            $result = ['success'=>$response->ok(),'body'=>$response->json()];

        } catch (\Throwable $th) {

            $result['error'] = $th->getMessage();
        }

        \Log::info('ImageDetectText->lambda->result',['result'=>$result]);

        return $result;
    }

    public function getTextFromImage($image_url){

        $result = $this->lambda($image_url);

        return $this->parseResult($result);
    
    }

    public function parseResult($data){

        $text = "ImageDetectTextBot 🤖:\r\n";
        $index = 1;

        if($data['success']){
            foreach($data['body']['data'] as $line){
                if($line['type']=='LINE'){
                    $text.= "{$index}) {$line['text']}\r\n";
                    $index++;
                }
            }
        }

        return $text;
    }
}