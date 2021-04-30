<?php

namespace Kefir\CallTracker;

trait CallTrackerConfigTrait
{
    /**
     * @var object
     */
    public $config;

    /**
     * @var string
     */
    public $configFile = __DIR__ . '/../../../../calltracker.json';

    /**
     * @var string
     */
    public $remoteHost = 'http://ct.kefir-media.ru';

    /**
     * Загружаем конфигурацию из файла
     * @return boolean
     */
    public function loadConfig()
    {
        if (file_exists($this->configFile))
        {
            $config = file_get_contents($this->configFile);

            if (is_string($config) && is_object(json_decode($config)))
            {
                $this->config = json_decode($config);

                return true;
            }
        }

        return false;
    }

    /**
     * Обновление конфигурационного файла
     * @param integer|null $domain_id
     * @return boolean
     */
    public function syncConfig($domain_id = null)
    {
        if ($domain_id = $domain_id ?? $this->config->domain_id)
        {
            $curl = curl_init();

            curl_setopt($curl, CURLOPT_URL,  $this->remoteHost . '/api/domain/' . $domain_id);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_USERAGENT , "CallTracker Package");

            $response = curl_exec($curl);
            curl_close($curl);

            if (is_string($response) && is_object(json_decode($response)))
            {
                $config = json_decode($response);

                if (isset($config->status) && $config->status)
                {
                    $file = fopen($this->configFile, 'w');
                    fwrite($file, $response);
                    fclose($file);

                    $this->config = $config;

                    return true;
                }
            }
        }

        return false;
    }
}