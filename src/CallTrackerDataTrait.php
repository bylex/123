<?php

namespace Kefir\CallTracker;

trait CallTrackerDataTrait
{
    /**
     * @var object|null
     */
    public $region;

    /**
     * @var object|null
     */
    public $partner;

    /**
     * @var string|null
     */
    public $track;

    /**
     * @var object|null
     */
    public $phone;

    /**
     * @var integer|null
     */
    public $city_id;

    /**
     * @var integer|null
     */
    public $site_id;

    /**
     * @var integer|null
     */
    public $line_id;

    /**
     * Проверяем, есть ли данные для подмены
     *
     * @return boolean
     */
    public function hasData()
    {
        return (is_object($this->phone) && $this->site_id) ? true : false;
    }

    /**
     * Создание словаря для кодирования/декодирования подменной информации
     *
     * @return array
     */
    protected function makeDictionary()
    {
        $symbols = array_merge(range(48, 57), range(65, 90), range(97, 122));
        $offset = $this->config->project_id % count($symbols);

        return array_merge(array_slice($symbols, $offset), array_slice($symbols, 0, $offset));
    }

    /**
     * Кодирование подменной информации
     *
     * @param $data
     * @return string
     */
    public function encodeData($data)
    {
        $data = is_array($data) ? implode(':', $data) : $data;
        $dictionary = $this->makeDictionary();
        $result = '';

        while (strlen($data) > 0)
        {
            if ($data[0] === ':')
            {
                $offset = 1;
                $chr = end($dictionary);
            }
            else
            {
                if ((isset($data[1]) && $data[1] === ':') || $data[0] == '0')
                {
                    $offset = 1;
                }
                else if (substr($data, 0, 2) >= count($dictionary) - 1)
                {
                    $offset = 1;
                }
                else
                {
                    $offset = 2;
                }

                $chr = $dictionary[substr($data, 0, $offset)];
            }

            $result .= chr($chr);
            $data = substr($data, $offset);
        }

        return $result;
    }

    /**
     * Декодирование подменной информации
     *
     * @param string $data
     * @return array|boolean
     */
    public function decodeData($data)
    {
        $result = '';
        $dictionary = $this->makeDictionary();
        $delimiter = chr(end($dictionary));

        while (strlen($data) > 0)
        {
            if ($data[0] === $delimiter)
            {
                $result .= ':';
            }
            else
            {
                $result .= array_search(ord($data), $dictionary);
            }

            $data = substr($data, 1);
        }

        return preg_match('/^(\d{10}):(\d{1,6}):?(\d|:)*$/', $result) ? explode(':', $result) : false;
    }

    /**
     * Установка города по ID
     *
     * @param integer|null $city_id
     * @return void
     */
    public function setCity($city_id = null)
    {
        if ($city_id && isset($this->config->regions->$city_id))
        {
            $this->city_id = (int) $city_id;
            $this->region = $this->config->regions->$city_id;

            if ($this->region->default->phone && $this->region->default->site)
            {
                $this->setPhone($this->region->default->phone);
                $this->setSiteId($this->region->default->site);
                $this->setLineId($this->region->default->line);
            }
        }
        else
        {
            $this->city_id = $this->region = null;
        }
    }

    /**
     * Поиск или раcшифровка подмены и сохранение данных
     *
     * @param string|null $track
     * @return void
     */
    public function setTrack($track = null)
    {
        if (!$track && isset($_GET[$this->config->tracker_param]))
        {
            $track = $_GET[$this->config->tracker_param];
        }
        elseif (!$track && isset($_SESSION['CT_KEY']))
        {
            $track = $_SESSION['CT_KEY'];
        }

        if ($track && (!isset($_SESSION['CT_KEY']) || $track != $_SESSION['CT_KEY']))
        {
            $_SESSION['CT_KEY'] = $track;
        }

        if ($track && $data = $this->decodeData($track))
        {
            $this->setPhone($data[0]);
            $this->setSiteId($data[1]);
            $this->setLineId($data[2] ?? null);
        }

        if ($track && $this->region)
        {
            if ($track && isset($this->region->trackers->$track))
            {
                $this->setPhone($this->region->trackers->$track->phone);
                $this->setSiteId($this->region->trackers->$track->site);
                $this->setLineId($this->region->trackers->$track->line);
            }
        }

        $this->track = $track;
    }

    /**
     * Отдельная подмена для партнеров.
     *
     * @param string $phone
     * @return void
     */
    public function setPartner()
    {
        $encryptedData = $_GET[$this->config->partner->key ?? 'partner'] ?? null;

        $decryptedData = $encryptedData ? openssl_decrypt(
            $encryptedData,
            'AES-256-CBC',
            $this->config->partner->passphrase ?? '4etA2j2mqE6a2StI',
            0,
            $this->config->partner->iv ?? 'j4dgM8l3kHi9m8L3') : null;

        if ($decryptedData && is_object(json_decode($decryptedData)))
        {
            $this->partner = $_SESSION['CT_PARTNER'] = json_decode($decryptedData);
        }
        elseif (!$this->partner && isset($_SESSION['CT_PARTNER']))
        {
            $this->partner = $_SESSION['CT_PARTNER'];
        }

        if (isset($this->partner->phone))
        {
            $this->setPhone($this->partner->phone);
        }
    }

    /**
     * Установка телефона.
     *
     * @param string $phone
     * @return void
     */
    public function setPhone($phone = null)
    {
        $this->phone = ($phone) ? (object) [
            'base' => $phone,
            'link' => '+7' . $phone,
            'full' => preg_replace('/^(\d{3})(\d{3})(\d{2})(\d{2})$/','+7 ($1) $2-$3-$4', $phone),
            'code' => substr($phone, 0, 3),
            'number' => preg_replace('/^(\d{3})(\d{3})(\d{2})(\d{2})$/','$2-$3-$4', $phone),
        ] : $phone;
    }

    /**
     * Получение телефона с возможностью указания форматов.
     *
     * @param array $patterns
     * @return object|null
     */
    public function getPhone($patterns = [])
    {
        if (is_object($this->phone))
        {
            foreach ($patterns as $name => $pattern)
            {
                $this->phone->$name = $this->getPhoneFormatted($pattern);
            }
        }

        return $this->phone;
    }

    /**
     * Получение телефона в определенном формате.
     *
     * @param string $pattern
     * @return array|null
     */
    public function getPhoneFormatted($pattern)
    {
        return preg_replace('/^(\d{3})(\d{3})(\d{2})(\d{2})$/', $pattern, $this->phone->base ?? $this->phone);
    }

    /**
     * Установка кода сайта.
     *
     * @param string $site_id
     * @return void
     */
    public function setSiteId($site_id = null)
    {
        $this->site_id = $site_id > 0 ? (int) $site_id : $site_id;
    }

    /**
     * Установка кода линии.
     *
     * @param string $line_id
     * @return void
     */
    public function setLineId($line_id = null)
    {
        $this->line_id = $line_id > 0 ? (int) $line_id : $line_id;
    }

    /**
     * Удаление текущего идентификатора подмены
     *
     * @return void
     */
    public function forget()
    {
        $_SESSION['CT_KEY'] = null;

        $this->phone = null;
        $this->city_id = null;
        $this->site_id = null;
        $this->line_id = null;
    }
}