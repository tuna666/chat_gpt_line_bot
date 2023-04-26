<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use GuzzleHttp\Client;

class LineBotController extends Controller
{
    private $lineBot;

    public function __construct()
    {
        $httpClient = new CurlHTTPClient(env('LINEBOT_CHANNEL_ACCESS_TOKEN'));
        $this->lineBot = new LINEBot($httpClient, ['channelSecret' => env('LINEBOT_CHANNEL_SECRET')]);
    }

    public function callback(Request $request)
    {
        $signature = $request->header('X-Line-Signature');

        if (empty($signature)) {
            abort(400, 'Invalid signature');
        }

        try {
            $events = $this->lineBot->parseEventRequest($request->getContent(), $signature);
        } catch (\Exception $e) {
            abort(400, 'Invalid request');
        }

        foreach ($events as $event) {
            if ($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage) {
                $textMessage = $event->getText();
                // ここでChatGPT APIと連携し、応答を取得します。
                $responseText = $this->getResponseFromChatGPT($textMessage);

                $replyToken = $event->getReplyToken();
                $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($responseText);
                $this->lineBot->replyMessage($replyToken, $textMessageBuilder);
            }
        }

        return response('OK', 200);
    }

    private function getResponseFromChatGPT($inputText)
    {
        $client = new Client();
        $response = $client->post('https://api.openai.com/v1/engines/davinci-codex/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'prompt' => $inputText,
                'max_tokens' => 50,
            ],
        ]);

        $responseBody = json_decode($response->getBody(), true);
        return $responseBody['choices'][0]['text'];
    }
}
