<?php

use Aws\BedrockAgentRuntime\BedrockAgentRuntimeClient;
use Aws\BedrockRuntime\BedrockRuntimeClient;

class GEOOPT_AWS_Provider implements GEOOPT_Provider {


    private $bedrock_agent_runtime_client;
    private $bedrock_runtime_client;
    private $options;

    public function __construct()
    {
        //load library
        if( !class_exists('Aws\Bedrock\BedrockClient') )
            require GEOOPT_DIR . '/vendor/autoload.php';

        $this->options = get_option('geoopt_settings', []);
    }

    /**
     * @param $post_url
     * @param $summary
     * @param int $number_of_results
     * @return array
     * @throws Exception
     */
    public function retrieve($post_url, $summary, $number_of_results=2): array
    {
        //todo: remove
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        $post_url = str_replace($host, 'www.transdev.com', $post_url);
        $lang = 'fr';

        $related_context = [];
        $agent_runtime = $this->get_bedrock_agent_runtime_client();

        $query_text = mb_substr($summary, 0, 900);

        $retrieve_response = $agent_runtime->retrieve([
            'knowledgeBaseId' => $this->options['kb_id'],
            'retrievalQuery'  => ['text' => $query_text],
            'retrievalConfiguration' => [
                'managedSearchConfiguration' => [
                    'numberOfResults' => $number_of_results,
                    'rerankingModelType' => 'NONE',
                    'filter'=> [
                        "andAll"=> [
                            ["equals"=> ["key"=> "language", "value"=> $lang]],
                            ["notEquals"=> ["key"=> "url", "value"=> $post_url]],
                        ]
                    ]
                ]
            ]
        ]);

        $results = $retrieve_response['retrievalResults'] ?? [];

        if (!empty($results)) {

            foreach ($results as $res) {

                $meta = $res['metadata'] ?? [];
                $url = $meta['url'] ?? '';

                $title = $meta['title'] ?? 'Source liée';
                $related_context[] = ['title'=>$title, 'url'=>$url, 'text'=>$res['content']['text']];
            }
        }

        return $related_context;
    }

    /**
     * @param $prompt
     * @param array $inferenceConfig
     * @param string $system_prompt
     * @param bool $decode
     * @return string|array
     * @throws Exception
     */
    public function converse($prompt, $inferenceConfig=[], $system_prompt='', $decode=false): array|string
    {
        if( empty($inferenceConfig) ){

            $inferenceConfig = [
                'maxTokens' => 1,
                'temperature' => 0.0
            ];
        }

        $runtime = $this->get_bedrock_runtime_client();

        $args = [
            'modelId' => $this->options['model']??'eu.anthropic.claude-sonnet-4-6',
            'messages' => [[
                'role' => 'user',
                'content' => [['text' => $prompt]]]
            ],
            'inferenceConfig' => $inferenceConfig
        ];

        if( !empty($system_prompt) ){

            $args['system'] = [
                ['text' => $system_prompt]
            ];
        }

        $output = $runtime->converse($args);

        $token_usage = $output['usage']['totalTokens']??0;
        $total_tokens = get_option('geoopt_total_tokens', 0);

        update_option('geoopt_total_tokens', $total_tokens + $token_usage);

        $text = $output['output']['message']['content'][0]['text']??'';

        if( $decode )
            $text = json_decode($text, true);

        return $text;
    }

    /**
     * @throws Exception
     */
    public function check_connection_handler(): bool
    {
        return (bool)$this->converse('ping');
    }

    /**
     * @throws Exception
     */
    public function get_credentials() {

        $access_key = GEOOPT_Helper::decrypt_secret($this->options['aws_access_key_id']??'');
        $secret_key = GEOOPT_Helper::decrypt_secret($this->options['aws_secret_access_key']??'');
        $region = $this->options['aws_region']??'';

        if (empty($access_key) || empty($secret_key) || empty($region))
            throw new \Exception('Identifiants AWS non renseignés.');

        return [
            'region'      => $region,
            'version'     => 'latest',
            'credentials' => [
                'key'    => $access_key,
                'secret' => $secret_key,
            ]
        ];
    }

    /**
     * @throws Exception
     */
    private function get_bedrock_runtime_client() {

        if( is_null($this->bedrock_runtime_client) ){

            $credentials = $this->get_credentials();
            $this->bedrock_runtime_client = new BedrockRuntimeClient($credentials);
        }

        return $this->bedrock_runtime_client;
    }

    /**
     * @throws Exception
     */
    private function get_bedrock_agent_runtime_client() {

        if( is_null($this->bedrock_agent_runtime_client) ){

            $credentials = $this->get_credentials();
            $this->bedrock_agent_runtime_client = new BedrockAgentRuntimeClient($credentials);
        }

        return $this->bedrock_agent_runtime_client;
    }

    public function get_name(): string
    {
        return 'AWS';
    }
}
