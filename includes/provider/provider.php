<?php

interface GEOOPT_Provider{

    /**
     * @param int $post_url
     * @param string $summary
     * @param int $number_of_results
     * @return array
     * @throws Exception
     */
    public function retrieve(int $post_url, string $summary, int $number_of_results=2): array;

    /**
     * @param $prompt
     * @param array $inferenceConfig
     * @param string $system_prompt
     * @param bool $decode
     * @return string|array
     * @throws Exception
     */
    public function converse($prompt, array $inferenceConfig=[], string $system_prompt='', bool $decode=false): string|array;

    /**
     * @throws Exception
     */
    public function check_connection_handler():bool;

    public function get_name():string;
}
