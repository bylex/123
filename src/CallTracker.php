<?php

namespace Kefir\CallTracker;

use Composer\Script\Event;

class CallTracker
{
    use CallTrackerConfigTrait;
    use CallTrackerDataTrait;

    public $apiPath = '/calltracker';

    /**
     * Выполнение операций при создании класса
     * @param array $options
     * @return void
     */
    public function __construct($options = [])
    {
        if (session_status() == PHP_SESSION_NONE)
        {
            session_start();
        }

        foreach ($options as $name => $value)
        {
            if (isset($this->$name)) $this->$name = $value;
        }

        if (strpos($_SERVER['REQUEST_URI'], $this->apiPath) === 0)
        {
            $this->api();
        }

        $this->loadConfig();
    }

    /**
     * Выполнение запроса к CallTracker
     * @return void
     */
    public function api()
    {
        header("HTTP/1.1 200 OK");

        if (isset($_GET['sync']))
        {
            $domain_id = $_GET['domain_id'];

            if ($this->syncConfig((!$this->loadConfig() && $domain_id) ? $domain_id : null))
            {
                $result = ['status' => true, 'message' => 'Синхронизация конфигурации успешно выполнена!'];
            }
            else
            {
                $result = ['status' => false, 'message' => 'Синхронизация конфигурации не удалась!'];
            }

            echo $domain_id ? $result['message'] : json_encode($result);
        }

        if (isset($_GET['data']))
        {
            $this->setCity($_GET['city_id']);
            $this->setTrack($_GET['track']);

            echo json_encode([
                'phone' => $this->phone,
                'site_id' => $this->site_id,
                'line_id' => $this->line_id,
            ]);
        }

        exit();
    }
}