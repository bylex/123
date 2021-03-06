# CallTracker - пакет Composer для отслеживания звонков и заявок

Данный пакет предназначен для подмены основных данных, таких как телефон, код сайта и код линии. При наличии в URL 
определенного параметра и идентификатора подмены - пакет возвращает телефон, код сайта и код линии которые им соответствуют.
Если идентификатор подмены отсутствует в ссылке, то будет возвращены данные по умолчанию.

Например при первом заходе на сайт по ссылке из рекламного объявления: http://example.com/?param=rnd - телефон, код сайта 
и код линии подменятся на те, что соответствуют идентификатору «rnd». Все такие соответствия прописываются в специальном 
менеджере подмен [ct.kefir-media.ru](http://ct.kefir-media.ru).

Цель таких подмен - возможность корректного отслеживания источника заявок. Как при звонке по телефону, так и при отправке 
заявки через форму на сайте.

## 1. Требования

1.1. Наличие PHP версии не ниже 7.

1.2. Наличие расширения для PHP - «ext-json». По умолчанию обычно включено.

## 2. Установка

2.1. Перед установкой убедитесь что проект и домен, в который добавляется CallTracker, присутствует в менеджере подмен [ct.kefir-media.ru](http://ct.kefir-media.ru).

2.2. Перейти в директорию проекта и установить пакет, выполнив последовательно две команды:
   ```
   composer config repositories.kefir/calltracker vcs https://repo.kefir-media.ru/tools/calltracker.git
   composer require kefir/calltracker:1.2.*
   ```

2.3. Добавьте в файл «.gitignore» запись для исключения конфигурационного файла «calltracker.json» из версионного контроля:
   ```
   calltracker.json
   ```

2.4. После установки пакета перейдите по URL, указав параметр «domain_id» - равный ID домена из менеджера подмен [ct.kefir-media.ru](http://ct.kefir-media.ru).
   ```
   /calltracker?sync&domain_id={ID домена}
   ```
Эта команда выполняется только если в корне проекта отсутствует конфигурационный файл «calltracker.json».
   
## 3. Интеграция

3.1. Стандартный пример интеграции на сайте:
   ```php
   require __DIR__ . '/../vendor/autoload.php';

   //  Инициализируем класс:

   $callTracker = new Kefir\CallTracker\CallTracker();
   
   //  Передаём код города. В примере 18413 - Москва:

   $callTracker->setCity(18413);
   
   //  Проверяем и задаем подмену (если в запросе передан параметр):

   $callTracker->setTrack();
   
   //  Для работы партнерских подмен следует добавить следующий вызов:
   
   $callTracker->setPartner();
   
   //  Выводим телефон на сайте в виде ссылки:
   //  <a href="tel:+74950001122">+7 (495) 000-11-22</a>

   echo sprintf('<a href="tel:%s">%s</a>', $callTracker->phone->link, $callTracker->phone->full);
   ```

3.2. Вариант интеграции с уже работающей системой городов на сайте:
   ```php
   $geoInfo['city'] = 18413; // Определенный заранее код города
   $geoInfo['track'] = 'pzh1' // Определенный заранее идентификатор подмены
   
   //  Инициализируем класс:

   $callTracker = new Kefir\CallTracker\CallTracker();
   
   //  После определения города, передаём код города (Обязательно):

   $callTracker->setCity($geoInfo['city']);
   
   //  При необходимости передаем идентификатор текущей подмены:

   $callTracker->setTrack($geoInfo['track']);
   
   //  Проверяем, имеются ли новые данные для подмены
   //  Если так, то запрашиваем нужные данные
   //  и записываем в сессию, меняя текущие данные
    
   if($callTracker->hasData())
   {
       $geoInfo['phone'] = (array) $callTracker->phone;
       $geoInfo['site'] = $callTracker->site_id;
       $geoInfo['line'] = $callTracker->line_id;
   }
   ```

3.3. Телефон (phone), код сайта (site_id) и код линии (line_id):
   ```php
   echo $callTracker->phone->base // 4950001122
   echo $callTracker->phone->link // '+74950001122'
   echo $callTracker->phone->full // '+7 (495) 000-11-22'
   echo $callTracker->phone->code // '495'
   echo $callTracker->phone->number // '000-11-22'
    
   // Если есть данные для подмены или данные по умолчанию, 
   // то при обращении к $callTracker->phone - будет возвращен 
   // объект со всеми свойствами, перечисленными выше.
   // 
   // Если вместо объекта нужен массив, то преобразовываем его так:

   $phoneArray = (array) $callTracker->phone;

   // [
   //    'base' => 4950001122,
   //    'link' => '+74950001122',
   //    'full' => '+7 (495) 000-11-22',
   //    'code' => '495',
   //    'number' => '000-11-22',
   // ]
   
   // Код сайта:

   echo $callTracker->site_id; // 789

   // Код линии (может быть null)

   echo $callTracker->line_id; // 2121
    
   // Если никаких данных нет, то все атрибуты вернут null
   ```
   
3.4. В случае необходимости можно задать иные форматы для телефона:
   ```php
   $phoneFormatted = $callTracker->getPhone([
       'full' => '8 [$1] $2-$3-$4',
       'number' => '$2$3$4',
       'custom' => '<span>$1</span> $2-$3$4'
   ]);

   echo $phoneFormatted->full; // '8 [495] 000-11-22'
   echo $phoneFormatted->number; // '0001122'
   echo $phoneFormatted->custom; // '<span>495</span> 000-1122'
   ```

3.5. При необходимости подмены специфичной информации для определенных идентификаторов - можно использовать свойство «track» для создания условий. Например:
   ```php
   <!--
   //  Нужно выводить элемент только если
   //  применена подмена с идетификатором «abtest»
   -->
   
   <?php if ($callTracker->track == 'abtest'): ?>
   
      <div> Специальная скидка 0% для пользователей TikTok </div>

   <?php endif; ?>
   ```

3.6. Также при наличии на сайте возможности переключать города, рекомендуем очищать сессию подмены перед такой сменой. Ниже пример:
   ```php
   //  Инициализируем класс:
   
   $callTracker = new Kefir\CallTracker\CallTracker();

   //  Проверяем передан ли новый параметр города

   if ($_GET['city'] != $geoInfo['city'])
   {
       // Очищаем сессию подмены

       $callTracker->forget();
   }

   //  После определения города, передаём код города (Обязательно):

   $callTracker->setCity($geoInfo['city']);
   ```


## 4. Стандартные свойства класса CallTracker

* **phone** - Объект с телефоном в разных форматах:
   - **phone->base** - Неотформатированный. Пример: `4950123456`
   - **phone->link** - Для использования в ссылках. Пример: `+74950123456`
   - **phone->code** - Код телефонного номера. Пример: `495`
   - **phone->full** - Полный формат телефона. Пример: `+7 (495) 012-34-56`
   - **phone->number** - Основная часть телефона. Пример: `012-34-56`
* **city_id** - Код города. Пример: `18413`
* **site_id** - Код сайта. Пример: `210`
* **line_id** - Код линии. Пример: `5430`
* **track** - Задан только в случае, если применена подмена телефона. Содержит ключ подмены.


## 5. Партнерская подмена в CallTracker

При передаче зашифрованных данных через параметр «partner» (или другой, назначенный в конфигурации), данные дешифруются и сохраняются в свойство:

   ```php

   //  ...
   
   $callTracker->setPartner();
   
   //  Выводим нужные данные

   if ($callTracker->partner)
   {
       echo $callTracker->partner->id;
   }
   ```

Если среди переданных данных содержится «phone», то телефон на сайте автоматически меняется на указанный, не зависимо от выбранной подмены. Полный список данных ниже:

* **id** - ID партнера для передачи в заявку. Пример: `12345`
* **phone** - Телефон (10 цифр). Пример: `4991112233`
* **ym** - ID Яндекс Метрики. Пример: `45689101`
* **ga** - ID Google Analytics. Пример: `UA-54357522-1`