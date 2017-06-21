<?php
    
    /**
     * The base class for mirSushi.
     */
    class mirSushi {
        /* @var modX $modx */
        public $modx;
        public $cart;
        public $session_prefix = 'ms.';
        public $main_domain;
        
        const DEFAULT_TIMEZONE                = 'Asia/Krasnoyarsk';
        const INDEX_TEMPLATE                  = 'index.php.template';
        const YAK_TEMPLATE                    = 'assets/components/yandexmoney/connector_result.php.template';
        const TEMPLATE_ID_EMPTY               = 0;
        const TEMPLATE_ID_INDEX_PAGE          = 2;
        const TEMPLATE_ID_CATEGORY_SUSHI_PAGE = 3;
        const TEMPLATE_ID_CATEGORY_CAKE_PAGE  = 5;
        const TEMPLATE_ID_PRODUCT_PAGE        = 6;
        const TEMPLATE_ID_404_PAGE            = 7;
        const TEMPLATE_ID_COMMON_PAGE         = 8;
        const TEMPLATE_ID_TESTIMONIAL_PAGE    = 9;
        const TEMPLATE_ID_SINGLE_PAGE         = 10;
        const TEMPLATE_ID_NEWS_PAGE           = 11;
        const TEMPLATE_ID_SINGLE_BONUS_PAGE   = 12;
        const TEMPLATE_ID_SHIPPING_PAGE       = 13;
        const TEMPLATE_ID_ACCOUNT_PAGE        = 14;
        const TEMPLATE_ID_CART_PAGE           = 15;
        const TEMPLATE_ID_MAINTENANCE_PAGE    = 16;
        const TEMPLATE_ID_CHECKOUT_PAGE       = 17;
        const TEMPLATE_ID_SUCCESS_PAGE        = 18;
        const TEMPLATE_ID_ACTION_PAGE         = 19;
        const TEMPLATE_ID_SEARCH_PAGE         = 20;
        
        const ERROR_USER_NOT_REGISTERED       = 'not-registered';
        const ERROR_USER_CANT_CREATE_CUSTOMER = 'cant-create-customer';
        
        const ERROR_API_WRONG_REQUEST_FORMAT = 'Некорректный формат запроса';
        const ERROR_API_SOURCE_WRONG         = 'Неверный идентификатор 1C';
        const ERROR_DEFAULT                  = 'Упс... Что-то пошло не так, мы не можем обработать Ваш запрос. Вы можете обратиться к нам по телефону или заказать звонок.';
        
        const CATEGORY_TV_IMAGE_LG = 1;
        const CATEGORY_TV_IMAGE_XS = 2;
        const CATEGORY_TV_SORT     = 35;
        
        const PRODUCT_TV_GROUP_NAME        = 33;
        const PRODUCT_TV_GROUP_RESOURCE_ID = 34;
        const PRODUCT_TV_AVAILABLE         = 9;
        const PRODUCT_TV_PRICE             = 10;
        
        /**
         * @param modX  $modx
         * @param array $config
         */
        function __construct(modX &$modx, array $config = array()) {
            $this->modx =& $modx;
            
            $corePath     = $this->modx->getOption('mirsushi_core_path', $config, $this->modx->getOption('core_path') . 'components/mirsushi/');
            $assetsUrl    = $this->modx->getOption('mirsushi_assets_url', $config, $this->modx->getOption('assets_url') . 'components/mirsushi/');
            $connectorUrl = $assetsUrl . 'connector.php';
            
            $this->config = array_merge(array(
                'assetsUrl'    => $assetsUrl,
                'cssUrl'       => $assetsUrl . 'css/',
                'jsUrl'        => $assetsUrl . 'js/',
                'imagesUrl'    => $assetsUrl . 'images/',
                'connectorUrl' => $connectorUrl,
                
                'corePath'       => $corePath,
                'modelPath'      => $corePath . 'model/',
                'chunksPath'     => $corePath . 'elements/chunks/',
                'templatesPath'  => $corePath . 'elements/templates/',
                'chunkSuffix'    => '',
                'snippetsPath'   => $corePath . 'elements/snippets/',
                'processorsPath' => $corePath . 'processors/'
            ), $config);
            
            $this->modx->addPackage('mirsushi', $this->config['modelPath']);
            $this->modx->lexicon->load('mirsushi:default');
        }
        
        public function updateContexts() {
            $result = false;
            
            $template_path = MODX_BASE_PATH . self::INDEX_TEMPLATE;
            require MODX_BASE_PATH . 'domain.conf.php';
            
            if (!defined('DEFAULT_DOMAIN')) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, '[mirSushi] Could not determine DEFAULT_DOMAIN');
                
                return;
            }
            
            /** @var modSystemSetting $server_protocol */
            $server_protocol = $this->modx->getObject('modSystemSetting', array(
                'key' => 'server_protocol'
            ));
            $server_protocol = $server_protocol->value;
            
            /** * @var modContextSetting $default_http_host */
            $default_http_host = $this->modx->getObject('modContextSetting', array(
                'context_key' => 'web',
                'key'         => 'http_host'
            ));
            
            $default_http_host->value = DEFAULT_DOMAIN;
            $default_http_host->save();
            
            /** * @var modContextSetting $default_site_url */
            $default_site_url = $this->modx->getObject('modContextSetting', array(
                'context_key' => 'web',
                'key'         => 'site_url'
            ));
            
            $default_site_url->value = $server_protocol . '://' . DEFAULT_DOMAIN . '/';
            $default_site_url->save();
            
            /** * @var modContextSetting $api_http_host */
            $api_http_host = $this->modx->getObject('modContextSetting', array(
                'context_key' => 'api',
                'key'         => 'http_host'
            ));
            
            $api_http_host->value = 'api.' . DEFAULT_DOMAIN;
            $api_http_host->save();
            
            /** * @var modContextSetting $api_site_url */
            $api_site_url = $this->modx->getObject('modContextSetting', array(
                'context_key' => 'api',
                'key'         => 'site_url'
            ));
            
            $api_site_url->value = $server_protocol . '://api.' . DEFAULT_DOMAIN . '/';
            $api_site_url->save();
            
            /** * @var modSystemSetting $session_cookie_domain */
            $session_cookie_domain = $this->modx->getObject('modSystemSetting', array(
                'key' => 'session_cookie_domain'
            ));
            
            $session_cookie_domain->value    = '.' . DEFAULT_DOMAIN;
            $session_cookie_domain->editedon = time();
            $session_cookie_domain->save();
            
            /** @var mirSushiCities[] $cities */
            $cities = $this->modx->getIterator('mirSushiCities', array(
                'active' => 1
            ));
            
            if (file_exists($template_path)) {
                $template = file_get_contents($template_path);
                
                $switch = 'switch (strtolower(MODX_HTTP_HOST)) {';
                $switch .= 'case "api.' . DEFAULT_DOMAIN . ':80":case "api.' . DEFAULT_DOMAIN . '":date_default_timezone_set("' . self::DEFAULT_TIMEZONE . '");$modx->initialize("api");break;';
                
                foreach ($cities as $city) {
                    // Проверяем наличие контекста
                    $context = $this->modx->getObject('modContext', array(
                        'key' => $city->context_key
                    ));
                    
                    // Если контекст не обнаружен, то создаем контекст со всеми необходимыми страницами
                    if (!$context) {
                        /** @var modContext $context */
                        $context       = $this->modx->newObject('modContext');
                        $context->key  = $city->context_key;
                        $context->name = $city->name;
                        $context->save();
                        
                        /** * @var modContextSetting $setting */
                        $setting              = $this->modx->newObject('modContextSetting');
                        $setting->context_key = $city->context_key;
                        $setting->key         = 'base_url';
                        $setting->value       = '/';
                        $setting->xtype       = 'textfield';
                        $setting->area        = 'core';
                        $setting->save();
                        
                        $setting              = $this->modx->newObject('modContextSetting');
                        $setting->context_key = $city->context_key;
                        $setting->key         = 'http_host';
                        $setting->value       = $city->domain . DEFAULT_DOMAIN;
                        $setting->xtype       = 'textfield';
                        $setting->area        = 'core';
                        $setting->save();
                        
                        $setting              = $this->modx->newObject('modContextSetting');
                        $setting->context_key = $city->context_key;
                        $setting->key         = 'site_url';
                        $setting->value       = $server_protocol . '://' . $city->domain . '.' . DEFAULT_DOMAIN . '/';
                        $setting->xtype       = 'textfield';
                        $setting->area        = 'core';
                        $setting->save();
                    } else {
                        /** @var modContextSetting $setting */
                        $setting = $this->modx->getObject('modContextSetting', array(
                            'context_key' => $city->context_key,
                            'key'         => 'http_host'
                        ));
                        
                        if ($setting) {
                            $setting->value = $city->domain . '.' . DEFAULT_DOMAIN;
                            $setting->save();
                        }
                        
                        $setting = $this->modx->getObject('modContextSetting', array(
                            'context_key' => $city->context_key,
                            'key'         => 'site_url'
                        ));
                        
                        if ($setting) {
                            $setting->value = $server_protocol . '://' . $city->domain . '.' . DEFAULT_DOMAIN . '/';
                            $setting->save();
                        }
                    }

                    $this->updateDefaultResources($context->key);

                    // TODO: решить проблему с определением хоста
                    $switch .= 'case "' . $city->domain . '.' . DEFAULT_DOMAIN . ':80":case "' . $city->domain . '.' . DEFAULT_DOMAIN . '":date_default_timezone_set("' . $city->timezone . '");$modx->initialize("' . $city->context_key . '");break;';

                    // Обновляем наборы параметров для работы с ЯК
                    $property_set_name = $city->domain . '.' . DEFAULT_DOMAIN;

                    /** @var modPropertySet $property_set */
                    $property_set = $this->modx->getObject('modPropertySet', array('name' => $property_set_name));

                    if (!$property_set) {
                        /** @var modSnippet $snippet */
                        $snippet = $this->modx->getObject('modSnippet', array('name' => 'YandexMoney'));

                        if ($snippet) {
                            /** @var modPropertySet $property_set */
                            $property_set              = $this->modx->newObject('modPropertySet');
                            $property_set->name        = $property_set_name;
                            $property_set->category    = 0;
                            $property_set->description = '';
                            $property_set->properties  = $snippet->getProperties();

                            if ($property_set->save()) {
                                $snippet->addPropertySet($property_set_name);
                            }
                        }
                    }
                }

                $switch .= 'default:$modx->initialize("web");break;};';

                $template = str_replace('{{CONTEXTS_SWITCH}}', $switch, $template);

                $fh = fopen(MODX_BASE_PATH . 'index.php', 'w');
                fwrite($fh, $template);
                fclose($fh);

                // Обновляем обработчик запросов от ЯК
                $template_path = MODX_BASE_PATH . self::YAK_TEMPLATE;
                $template      = file_get_contents($template_path);
                $template      = str_replace('{{CONTEXTS_SWITCH}}', $switch, $template);

                $fh = fopen(MODX_BASE_PATH . 'assets/components/yandexmoney/connector_result.php', 'w');
                fwrite($fh, $template);
                fclose($fh);
            }
        }

        /**
         * Функция создает дефолтный набор страниц для города и возвращает id главной страницы
         *
         * @param string $context
         *
         * @return bool|int
         */
        public function updateDefaultResources($context) {
            /**
             * @var modContextSetting $setting
             * @var modResource       $resource
             */

            // Настройка company_address
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'company_address',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'company_address';
                $setting->value       = '';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка company_name
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'company_name',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'company_name';
                $setting->value       = '';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка contact_phone_1
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'contact_phone_1',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'contact_phone_1';
                $setting->value       = '';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка contact_phone_2
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'contact_phone_2',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'contact_phone_2';
                $setting->value       = '';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка mobile_app_android
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'mobile_app_android',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'mobile_app_android';
                $setting->value       = '';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка mobile_app_ios
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'mobile_app_ios',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'mobile_app_ios';
                $setting->value       = '';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка social_fb
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'social_fb',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'social_fb';
                $setting->value       = '';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка social_ok
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'social_ok',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'social_ok';
                $setting->value       = '';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка social_tw
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'social_tw',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'social_tw';
                $setting->value       = '';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка social_vk
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'social_vk',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'social_vk';
                $setting->value       = '';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка marketing_email
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'marketing_email',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'marketing_email';
                $setting->value       = 'reklama@mirsushi.net';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка user_agreement
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'user_agreement',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'user_agreement';
                $setting->value       = '/assets/theme/mirsushi.com/Оферта Мир Суши.pdf';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка marketing_email
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'marketing_email',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'marketing_email';
                $setting->value       = 'reklama@mirsushi.net';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка email_marketing
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'email_marketing',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'email_marketing';
                $setting->value       = 'reklama@mirsushi.net';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка email_purchase
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'email_purchase',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'email_purchase';
                $setting->value       = 'ohotea@mirsushi.net';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка email_finance
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'email_finance',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'email_finance';
                $setting->value       = 'finance@mirsushi.net';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка email_hr
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'email_hr',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'email_hr';
                $setting->value       = 'osov@mirsushi.net';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка notify_order_emails
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'notify_order_emails',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'notify_order_emails';
                $setting->value       = '';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка notify_call_emails
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'notify_call_emails',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'notify_call_emails';
                $setting->value       = '';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка notify_help_emails
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'notify_help_emails',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'notify_help_emails';
                $setting->value       = '';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка notify_recal_emails
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'notify_recal_emails',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'notify_recal_emails';
                $setting->value       = '';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка notify_order_emails
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'notify_order_emails',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'notify_order_emails';
                $setting->value       = '';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Настройка payments_list
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'payments_list',
                'context_key' => $context
            ));

            if (!$setting) {
                $setting              = $this->modx->newObject('modContextSetting');
                $setting->context_key = $context;
                $setting->key         = 'payments_list';
                $setting->value       = 'cash,card';
                $setting->xtype       = 'textfield';
                $setting->area        = 'core';
                $setting->save();
            }

            // Главная страница
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'site_start',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = 'Главная';
                $resource->template    = self::TEMPLATE_ID_INDEX_PAGE;
                $resource->published   = true;
                $resource->alias       = 'index';
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;

                if ($resource->save()) {
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'site_start';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Страница "Меню"
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'menu_page',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = 'Меню';
                $resource->template    = 0;
                $resource->published   = true;
                $resource->alias       = 'menyu';
                $resource->content     = '[[!FirstChildRedirect]]';
                $resource->richtext    = 0;
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;

                if ($resource->save()) {
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'menu_page';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Страница 404
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'error_page',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = '404';
                $resource->template    = self::TEMPLATE_ID_404_PAGE;
                $resource->published   = true;
                $resource->alias       = 'not-found';
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;
                $resource->richtext    = 0;
                $resource->hidemenu    = 1;
                $resource->searchable  = 0;

                if ($resource->save()) {
                    /** @var modContextSetting $setting */
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'error_page';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Страница "Отзывы"
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'testimonials_page',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = 'Отзывы';
                $resource->template    = self::TEMPLATE_ID_COMMON_PAGE;
                $resource->published   = true;
                $resource->alias       = 'otzyivyi';
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;
                $resource->richtext    = 0;
                $resource->content     = '[[!snptGetTestimonials]]';

                if ($resource->save()) {
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'testimonials_page';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Страница "О компании"
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'about_page',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = 'О компании';
                $resource->template    = self::TEMPLATE_ID_SINGLE_PAGE;
                $resource->published   = true;
                $resource->alias       = 'o-kompanii';
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;
                $resource->richtext    = 0;
                $resource->content     = '';

                if ($resource->save()) {
                    /** @var modContextSetting $setting */
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'about_page';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Страница "Кафе"
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'cafe_page',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = 'Кафе';
                $resource->template    = self::TEMPLATE_ID_SINGLE_PAGE;
                $resource->published   = true;
                $resource->alias       = 'cafe';
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;
                $resource->richtext    = 0;
                $resource->content     = '';

                if ($resource->save()) {
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'cafe_page';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Страница "Новости"
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'news_page',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = 'Новости';
                $resource->template    = self::TEMPLATE_ID_COMMON_PAGE;
                $resource->published   = true;
                $resource->alias       = 'news';
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;
                $resource->richtext    = 0;
                $resource->content     = '[[!snptGetNews]]';

                if ($resource->save()) {
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'news_page';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Страница "Акции"
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'action_page',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = 'Акции';
                $resource->template    = self::TEMPLATE_ID_COMMON_PAGE;
                $resource->published   = true;
                $resource->alias       = 'actions';
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;
                $resource->richtext    = 0;
                $resource->content     = '[[!snptGetActions]]';

                if ($resource->save()) {
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'action_page';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Страница "Бонусы"
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'bonus_page',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = 'Бонусы';
                $resource->template    = self::TEMPLATE_ID_SINGLE_BONUS_PAGE;
                $resource->published   = true;
                $resource->alias       = 'bonusi';
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;

                if ($resource->save()) {
                    /** @var modContextSetting $setting */
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'bonus_page';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Страница "Доставка"
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'shipping_page',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = 'Доставка';
                $resource->template    = self::TEMPLATE_ID_SHIPPING_PAGE;
                $resource->published   = true;
                $resource->alias       = 'dostavka';
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;
                $resource->richtext    = 0;
                $resource->content     = '';

                if ($resource->save()) {
                    /** @var modContextSetting $setting */
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'shipping_page';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Страница "Корзина"
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'cart_page',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = 'Корзина';
                $resource->template    = self::TEMPLATE_ID_CART_PAGE;
                $resource->published   = true;
                $resource->alias       = 'cart';
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;
                $resource->richtext    = 0;
                $resource->content     = '';
                $resource->hidemenu    = 1;

                if ($resource->save()) {
                    /** @var modContextSetting $setting */
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'cart_page';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Страница "Личный кабинет"
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'account_page',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = 'Личный кабинет';
                $resource->template    = self::TEMPLATE_ID_ACCOUNT_PAGE;
                $resource->published   = true;
                $resource->alias       = 'account';
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;
                $resource->richtext    = 0;
                $resource->content     = '';
                $resource->hidemenu    = 1;

                if ($resource->save()) {
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'account_page';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Страница "Выход"
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'logout_page',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = 'Выход';
                $resource->template    = self::TEMPLATE_ID_EMPTY;
                $resource->published   = true;
                $resource->alias       = 'logout';
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;
                $resource->richtext    = 0;
                $resource->content     = '[[!snptLogout]]';
                $resource->hidemenu    = 1;

                if ($resource->save()) {
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'logout_page';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Страница "Оформление заказа"
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'checkout_page',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = 'Оформление заказа';
                $resource->menutitle   = 'Корзина';
                $resource->template    = self::TEMPLATE_ID_CHECKOUT_PAGE;
                $resource->published   = true;
                $resource->alias       = 'checkout';
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;
                $resource->richtext    = 0;
                $resource->content     = '';
                $resource->hidemenu    = 1;

                if ($resource->save()) {
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'checkout_page';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Страница "Заказ Оформлен"
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'success_page',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = 'Заказ оформлен';
                $resource->template    = self::TEMPLATE_ID_SUCCESS_PAGE;
                $resource->published   = true;
                $resource->alias       = 'success';
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;
                $resource->richtext    = 0;
                $resource->content     = '';
                $resource->hidemenu    = 1;

                if ($resource->save()) {
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'success_page';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Страница "Поиск"
            $setting = $this->modx->getObject('modContextSetting', array(
                'key'         => 'search_page',
                'context_key' => $context
            ));

            if (!$setting) {
                $resource              = $this->modx->newObject('modResource');
                $resource->context_key = $context;
                $resource->pagetitle   = 'Поиск';
                $resource->template    = self::TEMPLATE_ID_SEARCH_PAGE;
                $resource->published   = true;
                $resource->alias       = 'poisk';
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;
                $resource->richtext    = 0;
                $resource->content     = '';
                $resource->hidemenu    = 1;

                if ($resource->save()) {
                    $setting              = $this->modx->newObject('modContextSetting');
                    $setting->context_key = $context;
                    $setting->key         = 'search_page';
                    $setting->value       = $resource->id;
                    $setting->xtype       = 'textfield';
                    $setting->area        = 'core';
                    $setting->save();
                }
            }

            // Системные настройки
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 's',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource              = $this->modx->newObject('modResource');
                $resource->contentType = 'text/html';
                $resource->pagetitle   = 'system';
                $resource->alias       = 's';
                $resource->published   = true;
                $resource->isfolder    = true;
                $resource->richtext    = 0;
                $resource->template    = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon = date('U');
                $resource->createdon   = $resource->publishedon;
                $resource->context_key = $context;
                $resource->content     = '';
                $resource->hidemenu    = 1;
                $resource->save();
            }

            $system_container = $resource->id;

            // addToCart
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'add-to-cart',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'addToCart';
                $resource->alias        = 'add-to-cart';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // getCartStepOne
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'get-cart-step-one',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'getCartStepOne';
                $resource->alias        = 'get-cart-step-one';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // increaseCartItem
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'increase-cart-item',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'increaseCartItem';
                $resource->alias        = 'increase-cart-item';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // decreaseCartItem
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'decrease-cart-item',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'decreaseCartItem';
                $resource->alias        = 'decrease-cart-item';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // removeCartItem
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'remove-cart-item',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'removeCartItem';
                $resource->alias        = 'remove-cart-item';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // getLoginConfirmCode
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'get-login-confirm-code',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'getLoginConfirmCode';
                $resource->alias        = 'get-login-confirm-code';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // getRegistrationConfirmCode
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'get-registration-confirm-code',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'getRegistrationConfirmCode';
                $resource->alias        = 'get-registration-confirm-code';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // confirmLogin
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'confirm-login',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'confirmLogin';
                $resource->alias        = 'confirm-login';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // confirmRegistration
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'confirm-registration',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'confirmRegistration';
                $resource->alias        = 'confirm-registration';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // getProduct
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'get-product',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'getProduct';
                $resource->alias        = 'get-product';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // getProducts
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'get-products',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'getProducts';
                $resource->alias        = 'get-products';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // getNews
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'get-news',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'getNews';
                $resource->alias        = 'get-news';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // getNewsItem
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'get-news-item',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'getNewsItem';
                $resource->alias        = 'get-news-item';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // getActionItem
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'get-action-item',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'getActionItem';
                $resource->alias        = 'get-action-item';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // sendCallback
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'send-callback',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'sendCallback';
                $resource->alias        = 'send-callback';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // checkCartStepOne
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'check-cart-step-one',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'checkCartStepOne';
                $resource->alias        = 'check-cart-step-one';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // getSurprise
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'get-surprise',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'getSurprise';
                $resource->alias        = 'get-surprise';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // getStreets
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'get-streets',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'getStreets';
                $resource->alias        = 'get-streets';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // newAddress
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'new-address',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'newAddress';
                $resource->alias        = 'new-address';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // updateAccountProfile
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'update-account-profile',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'updateAccountProfile';
                $resource->alias        = 'update-account-profile';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // sendFAQ
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'send-faq',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'sendFAQ';
                $resource->alias        = 'send-faq';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // sendTestimonial
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'send-testimonial',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'sendTestimonial';
                $resource->alias        = 'send-testimonial';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // increaseCartToppingItem
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'increase-cart-topping-item',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'increaseCartToppingItem';
                $resource->alias        = 'increase-cart-topping-item';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // decreaseCartToppingItem
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'decrease-cart-topping-item',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'decreaseCartToppingItem';
                $resource->alias        = 'decrease-cart-topping-item';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // increaseCartFlatwareItem
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'increase-cart-flatware-item',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'increaseCartFlatwareItem';
                $resource->alias        = 'increase-cart-flatware-item';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // decreaseCartFlatwareItem
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'decrease-cart-flatware-item',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'decreaseCartFlatwareItem';
                $resource->alias        = 'decrease-cart-flatware-item';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // addGiftToCart
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'add-gift-to-cart',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'addGiftToCart';
                $resource->alias        = 'add-gift-to-cart';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // getGroupProduct
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'get-group-product',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'getGroupProduct';
                $resource->alias        = 'get-group-product';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // clearCart
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'clear-cart',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'clearCart';
                $resource->alias        = 'clear-cart';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // repeatOrder
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'repeat-order',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'repeatOrder';
                $resource->alias        = 'repeat-order';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // getDeliveryDates
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'get-delivery-dates',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'getDeliveryDates';
                $resource->alias        = 'get-delivery-dates';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // getDeliveryTimes
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'get-delivery-times',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'getDeliveryTimes';
                $resource->alias        = 'get-delivery-times';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // getDeliveryCost
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'get-delivery-cost',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'getDeliveryCost';
                $resource->alias        = 'get-delivery-cost';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // checkCartStepTwo
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'check-cart-step-two',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'checkCartStepTwo';
                $resource->alias        = 'check-cart-step-two';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // orderConfirm
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'order-confirm',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'orderConfirm';
                $resource->alias        = 'order-confirm';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // socialAuth
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'social-auth',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'socialAuth';
                $resource->alias        = 'social-auth';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            // activatePromo
            $resource = $this->modx->getObject('modResource', array(
                'alias'       => 'activate-promo',
                'context_key' => $context
            ));

            if (!$resource) {
                $resource               = $this->modx->newObject('modResource');
                $resource->contentType  = 'application/json';
                $resource->content_type = 7;
                $resource->pagetitle    = 'activatePromo';
                $resource->alias        = 'activate-promo';
                $resource->parent       = $system_container;
                $resource->published    = true;
                $resource->isfolder     = false;
                $resource->richtext     = 0;
                $resource->template     = self::TEMPLATE_ID_EMPTY;
                $resource->publishedon  = date('U');
                $resource->createdon    = $resource->publishedon;
                $resource->context_key  = $context;
                $resource->content      = '[[!snpt' . ucfirst($resource->pagetitle) . ']]';
                $resource->hidemenu     = 1;
                $resource->save();
            }

            return;
        }

        /**
         * @return array
         */
        public function getAvailableCities() {
            $contexts = array();

            /** @var mirSushiCities[] $collection */
            $collection = $this->modx->getIterator('mirSushiCities', array(
                'active' => 1
            ));

            foreach ($collection as $item) {
                $contexts[$item->domain] = $item->name;
            }

            return $contexts;
        }

        /**
         * @param int $active
         *
         * @return mirSushiCities[]
         */
        public function getCities($active = 1) {
            $cities = array();

            /** @var mirSushiCities[] $cities_collection */
            $cities_collection = $this->modx->getIterator('mirSushiCities', array(
                'active' => $active
            ));

            foreach ($cities_collection as $city) {
                $cities[$city->id] = $city;
            }

            return $cities;
        }

        /**
         * @param string $term
         *
         * @return mirSushiStreets[]
         */
        public function getStreets($term = '') {
            $streets = array();

            $q = $this->modx->newQuery('mirSushiStreets');
            $q->innerJoin('mirSushiCities', 'mirSushiCities', 'mirSushiCities.id = mirSushiStreets.city_id');
            $q->where(array(
                'mirSushiCities.context_key' => $this->modx->context->key,
                'mirSushiStreets.name:LIKE'  => '%' . $term . '%'
            ));
            $q->sortby('mirSushiStreets.name');


            /** @var mirSushiStreets[] $streets_collection */
            $streets_collection = $this->modx->getIterator('mirSushiStreets', $q);

            foreach ($streets_collection as $street) {
                $streets[] = $street->toArray();
            }

            return $streets;
        }

        /**
         * @return array
         */
        public function getProductLabels() {
            $labels = array();

            /** @var mirSushiLabels[] $collection */
            $collection = $this->modx->getIterator('mirSushiLabels');

            foreach ($collection as $item) {
                $labels[$item->id] = $item->image;
            }

            return $labels;
        }

        /**
         * @param string $request_name
         * @param string $method
         * @param array  $request_data
         *
         * @return array|string
         */
        public function getServerData($request_name, $method, $request_data) {
            $json = array();

            switch ($request_name) {
                case '_snptGetSurprise_':
                    $json = array(
                        "response" => array(
                            "products" => array(
                                "c608478c-fa16-11e4-9752-0025907ad560",
                                "56e6ec0a-676c-11e5-ab7d-0025907ad560",
                                "0906d2f2-e82e-11e1-9c27-bcaec58caea2",
                                "d91a1237-e814-11e1-9c27-bcaec58caea2"
                            ),
                            "total"    => 1200
                        ),
                        'result'   => 'success'
                    );
                    break;
                default:
                    $session_before_request = $_SESSION;

                    $api_url = $this->modx->getOption('server_api_url', null, false);

                    if ($api_url) {
                        $request_url = $api_url . $method;

                        $request_time = date('Y-m-d H:i:s');

                        $ch = curl_init($request_url);

                        $post_data = array(
                            "requestId"        => time(),
                            "requestName"      => $request_name,
                            "requestSessionId" => session_id(),
                            "request"          => $request_data
                        );

                        /** @var mirSushiLog $log */
                        $log                         = $this->modx->newObject('mirSushiLog');
                        $log->datetime               = date('Y-m-d H:i:s');
                        $log->request_name           = $method;
                        $log->request_source         = $request_name;
                        $log->session_id             = session_id();
                        $log->request                = $request_data;
                        $log->session_before_request = $session_before_request;
                        $log->save();

                        curl_setopt_array($ch, array(
                            CURLOPT_POST           => true,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_HTTPHEADER     => array(
                                'Content-Type: application/json'
                            ),
                            CURLOPT_POSTFIELDS     => json_encode($post_data)
                        ));

                        $response = curl_exec($ch);

                        $log->response               = !json_decode($response, true) ? array() : json_decode($response, true);
                        $log->session_after_response = $_SESSION;
                        $log->save();

                        if ($response === false or !json_decode($response)) {
                            $message = 'API URL: ' . $request_url . '<br />';
                            $message .= 'Время запроса: ' . $request_time . '<br />';
                            $message .= 'Тело запроса: <pre>' . print_r($request_data, true) . '</pre><br />';

                            if ($response === false) {
                                $message .= 'Тело ответа: ' . curl_error($ch) . '<br />';
                            } elseif (!json_decode($response)) {
                                $message .= 'Тело ответа: ' . $response . '<br />';
                            } else {
                                $message .= 'Тело ответа: NULL<br />';
                            }

                            $this->sendEmailError('1C-api.' . $method, $message);
                        } else {
                            $json['result'] = 'success';
                            $json           = array_merge($json, json_decode($response, true));
                        }
                    } else {
                        $this->sendEmailError('1C-api.' . $method, "Could not find 1C api url\n\n-----------------------\n\n<pre>" . print_r($request_data, true) . '</pre>');
                        $this->modx->log(modX::LOG_LEVEL_ERROR, '[mirSushi] Could not find 1C api url.');
                    }
            }

            return $json;
        }

        public function getCart() {
            if (!isset($_SESSION[$this->session_prefix . 'products'])) {
                $_SESSION[$this->session_prefix . 'products'] = array();
            }

            if (!isset($_SESSION[$this->session_prefix . 'addition'])) {
                $_SESSION[$this->session_prefix . 'addition'] = array();
            }

            if (!isset($_SESSION[$this->session_prefix . 'flatware'])) {
                $_SESSION[$this->session_prefix . 'flatware'] = array();
            }

            return array(
                'products' => $this->getCartProducts(),
                'addition' => $this->getCartAddition(),
                'flatware' => $this->getCartFlatware(),
                'gifts'    => $this->getCartGifts()
            );
        }

        public function getCartProducts() {
            if (!isset($_SESSION[$this->session_prefix . 'products'])) {
                $_SESSION[$this->session_prefix . 'products'] = array();
            }

            return $_SESSION[$this->session_prefix . 'products'];
        }

        public function getCartAddition() {
            if (!isset($_SESSION[$this->session_prefix . 'addition'])) {
                $_SESSION[$this->session_prefix . 'addition'] = array();
            }

            return $_SESSION[$this->session_prefix . 'addition'];
        }

        public function getCartFlatware() {
            if (!isset($_SESSION[$this->session_prefix . 'flatware'])) {
                $_SESSION[$this->session_prefix . 'flatware'] = array();
            }

            return $_SESSION[$this->session_prefix . 'flatware'];
        }
/*
        public function getCartGifts() {
            if (!isset($_SESSION[$this->session_prefix . 'available_gifts'])) {
                $_SESSION[$this->session_prefix . 'available_gifts'] = array();
            }

            return $_SESSION[$this->session_prefix . 'available_gifts'];
        }
*/
        // переписал из-за явной ошибки!
        // когда получаем корзину клиента, нам нужны не доступные подарки, а те, что у него уже лежат в корзине!!!
        public function getCartGifts() {
            if (!isset($_SESSION[$this->session_prefix . 'gifts'])) {
                $_SESSION[$this->session_prefix . 'gifts'] = array();
            }

            return $_SESSION[$this->session_prefix . 'gifts'];
        }

        public function getCartShipping() {
            if (!isset($_SESSION[$this->session_prefix . 'shipping'])) {
                $_SESSION[$this->session_prefix . 'shipping'] = 0;
            }

            return $_SESSION[$this->session_prefix . 'shipping'];
        }

        public function getCartTotal() {
            $total = 0;

            $total += $this->getCartProductsTotal();
            $total += $this->getCartAdditionTotal();
            $total += $this->getCartFlatwareTotal();

            return $total;
        }

        public function getCartProductsTotal() {
            $total = 0;

            foreach ($this->getCartProducts() as $item) {
                $total += $item['total'];
            }

            return $total;
        }

        public function getCartAdditionTotal() {
            $total = 0;

            foreach ($this->getCartAddition() as $item) {
                $total += $item['total'];
            }

            return $total;
        }

        public function getCartFlatwareTotal() {
            $total = 0;

            foreach ($this->getCartFlatware() as $item) {
                $total += $item['total'];
            }

            return $total;
        }

        /**
         * @param string $code
         *
         * @return bool|int
         */
        public function productCodeToResourceId($code) {
            $q = $this->modx->newQuery('mirSushiProducts');
            $q->where(array(
                'code'        => $code,
                'context_key' => $this->modx->context->key
            ));
            $q->limit(1);

            /** @var mirSushiProducts $product */
            $product = $this->modx->getObject('mirSushiProducts', $q);

            if ($product) {
                return $product->resource_id;
            }

            return false;
        }

        /**
         * @param integer $resource_id
         *
         * @return bool|string
         */
        public function productResourceIdToCode($resource_id) {
            $q = $this->modx->newQuery('mirSushiProducts');
            $q->where(array(
                'resource_id' => $resource_id,
                'context_key' => $this->modx->context->key
            ));
            $q->limit(1);

            /** @var mirSushiProducts $product */
            $product = $this->modx->getObject('mirSushiProducts', $q);

            if ($product) {
                return $product->code;
            }

            return false;
        }

        public function addToCartProduct($product_id, $quantity = 1, $action_id = '', $action_type = '') {
            $cart = $_SESSION[$this->session_prefix . 'products'];

            $product_code = $this->productResourceIdToCode($product_id);

            if ($product_code) {
                if (array_key_exists($product_code, $cart)) {
                    $cart[$product_code]['quantity'] += $quantity;
                    $cart[$product_code]['total'] = $cart[$product_code]['quantity'] * $cart[$product_code]['price'];

                    if ($cart[$product_code]['quantity'] <= 0) {
                        unset($cart[$product_code]);
                    }
                } else {
                    /** @var modResource $resource */
                    $resource = $this->modx->getObject('modResource', $product_id);

                    if ($resource) {
                        $tvs = $this->modx->runSnippet('snptGetResourceTvs', array(
                            'resource_id' => $product_id
                        ));

                        $cart[$product_code] = array(
                            'product_id'  => $product_id,
                            'price'       => $tvs['tv-product-price'],
                            'quantity'    => $quantity,
                            'total'       => $tvs['tv-product-price']*$quantity,
                            'action_id'   => $action_id,
                            'action_type' => $action_type
                        );
                    }
                }

                $total = 0;
                foreach ($cart as $item) {
                    $total += $item['total'];
                }

                $_SESSION[$this->session_prefix . 'products'] = $cart;
                $_SESSION[$this->session_prefix . 'addition'] = array();
                $_SESSION[$this->session_prefix . 'flatware'] = array();
            }
        }

        public function addToCartGift($product_id, $quantity = 1, $action_id = '', $action_type = '') {
            $cart = array();//$_SESSION[$this->session_prefix . 'gifts'];

            /** @var modResource $resource */
            $resource = $this->modx->getObject('modResource', $product_id);

            if ($resource) {
                $tvs = $this->modx->runSnippet('snptGetResourceTvs', array(
                    'resource_id' => $resource->id
                ));

                $product_code = $this->productResourceIdToCode($product_id);
/*
                if (isset($_SESSION[$this->session_prefix . 'gifts'][$product_code])) {
                    $price = $_SESSION[$this->session_prefix . 'gifts'][$product_code]['price'];
                } else {
*/
//                    $price = $tvs['tv-product-price'];
// теперь подарок - это тот же продукт, только с нулевой ценой. Поэтому цену из продукта вытаскивать нельзя.
                    $price = 0;
//                }

                $cart[$product_code] = array(
                    'product_id'  => $product_id,
                    'price'       => $price,
                    'quantity'    => $quantity,
                    'total'       => $price,
                    'action_id'   => $action_id,
                    'action_type' => $action_type
                );
            }

            $_SESSION[$this->session_prefix . 'gifts'] = $cart;
        }

        public function addToCartAddition($product_id, $quantity = 1, $action_id = '', $action_type = '') {
            $cart = $_SESSION[$this->session_prefix . 'addition'];

            $product_code = $this->productResourceIdToCode($product_id);

            if ($product_code) {
                if (array_key_exists($product_code, $cart)) {
                    $cart[$product_code]['quantity'] += $quantity;
                    $cart[$product_code]['total'] = $cart[$product_code]['quantity'] * $cart[$product_code]['price'];

                    if ($cart[$product_code]['quantity'] <= 0) {
                        unset($cart[$product_code]);
                    }
                } else {
                    /** @var modResource $resource */
                    $resource = $this->modx->getObject('modResource', $product_id);

                    if ($resource) {
                        $tvs = $this->modx->runSnippet('snptGetResourceTvs', array(
                            'resource_id' => $product_id
                        ));

                        $cart[$product_code] = array(
                            'product_id'  => $product_id,
                            'price'       => $tvs['tv-product-price'],
                            'quantity'    => $quantity,
                            'total'       => $tvs['tv-product-price']*$quantity,
                            'action_id'   => $action_id,
                            'action_type' => $action_type
                        );
                    }
                }

                $total = 0;
                foreach ($cart as $item) {
                    $total += $item['total'];
                }

                $_SESSION[$this->session_prefix . 'addition'] = $cart;
            }
        }

        public function addToCartFlatware($product_id, $quantity = 1, $action_id = '', $action_type = '') {
            $cart = $_SESSION[$this->session_prefix . 'flatware'];

            $product_code = $this->productResourceIdToCode($product_id);

            if ($product_code) {
                if (array_key_exists($product_code, $cart)) {
                    $cart[$product_code]['quantity'] += $quantity;
                    $cart[$product_code]['total'] = $cart[$product_code]['quantity'] * $cart[$product_code]['price'];

                    if ($cart[$product_code]['quantity'] <= 0) {
                        unset($cart[$product_code]);
                    }
                } else {
                    /** @var modResource $resource */
                    $resource = $this->modx->getObject('modResource', $product_id);

                    if ($resource) {
                        $tvs = $this->modx->runSnippet('snptGetResourceTvs', array(
                            'resource_id' => $resource->id
                        ));

                        $cart[$product_code] = array(
                            'product_id'  => $product_id,
                            'price'       => $tvs['tv-product-price'],
                            'quantity'    => $quantity,
                            'total'       => $tvs['tv-product-price']*$quantity,
                            'action_id'   => $action_id,
                            'action_type' => $action_type
                        );
                    }
                }

                $total = 0;
                foreach ($cart as $item) {
                    $total += $item['total'];
                }

                $_SESSION[$this->session_prefix . 'flatware'] = $cart;
            }
        }

        public function removeFromCartProduct($product_id) {
            $product_code = $this->productResourceIdToCode($product_id);

            if ($product_code) {
                if (isset($_SESSION[$this->session_prefix . 'products'][$product_code])) {
                    unset($_SESSION[$this->session_prefix . 'products'][$product_code]);
                }

                if (isset($_SESSION[$this->session_prefix . 'gifts'][$product_code])) {
                    unset($_SESSION[$this->session_prefix . 'gifts'][$product_code]);
                }
            }

            if (sizeof($_SESSION[$this->session_prefix . 'products']) == 0) {
                $this->clearCart();
            }

            $_SESSION[$this->session_prefix . 'gifts']    = array();
            $_SESSION[$this->session_prefix . 'addition'] = array();
            $_SESSION[$this->session_prefix . 'flatware'] = array();
        }

        public function addCartShipping($cost) {
            if (!isset($_SESSION[$this->session_prefix . 'shipping'])) {
                $_SESSION[$this->session_prefix . 'shipping'] = 0;
            }

            $_SESSION[$this->session_prefix . 'shipping'] = $cost;
        }

        public function clearCart() {
            $_SESSION[$this->session_prefix . 'products'] = array();
            $_SESSION[$this->session_prefix . 'addition'] = array();
            $_SESSION[$this->session_prefix . 'flatware'] = array();
            $_SESSION[$this->session_prefix . 'gifts']    = array();

            unset($_SESSION[$this->session_prefix . 'available_addition']);
            unset($_SESSION[$this->session_prefix . 'available_flatware']);
            unset($_SESSION[$this->session_prefix . 'available_gifts']);
            unset($_SESSION[$this->session_prefix . 'selected_gift']);

            unset($_SESSION[$this->session_prefix . 'checkout.step.1.validate']);
            unset($_SESSION[$this->session_prefix . 'checkout.step.2.validate']);
        }

        public function sendSMS($phone, $code, $retry = 0) {

            // приводит номер к формату 7ХХХХХХХХХХ
            function normalize_number($num)
            {
                $destinationAddress = $num;
                $destinationAddress = trim($destinationAddress);
                // если строка начинается с "+", то просто выкинем его
                if (substr($destinationAddress, 0, 1) == '+') {
                    $destinationAddress = substr($destinationAddress, 1, strlen($destinationAddress)-1);
                }

                // если длина строки 10, то просто добавляем 7 в начало
                if (strlen($destinationAddress)==10) {
                    $destinationAddress = '7'.$destinationAddress;
                }
                // если длина строки 11 и начинается на 8, то просто заменяем 8 на 7
                if (strlen($destinationAddress)==11 and substr($destinationAddress, 0, 1) == '8'){
                    $destinationAddress = '7'.substr($destinationAddress, 1,10);
                }
                return $destinationAddress;
            }

            $phone = normalize_number($phone);

            $sms_gateway = $this->modx->getOption('sms_gateway');
            //$sms_gateway = 'http://sms.mirsushi.net/sendsms.php';

            if (!$sms_gateway) {
                $message = 'Время запроса: ' . date('Y-m-d H:i:s') . '<br />';
                $message .= 'Тело ответа: В настройках отсутствует параметр sms_gateway<br />';

                $this->sendEmailError('core.sendSMS', $message);

                return false;
            }

            $sms_url = (strpos($sms_gateway, 'sendsms') !== false)
                ? $sms_gateway . '?from=tele2&number=' . $phone . '&text=' . $code . '&retry=' . $retry
                : $sms_gateway . '?destinationAddress=' . $phone . '&data=' . $code . '&retry=' . $retry;

            $response = file_get_contents($sms_url);

            if (strpos($response, 'Accepted for delivery') !== false) {
                return true;
            } else {
                $message = 'API URL: ' . $sms_url . '<br />';
                $message .= 'Время запроса: ' . date('Y-m-d H:i:s') . '<br />';

                if (!$response) {
                    $message .= 'Тело ответа: NULL<br />';
                } else {
                    $message .= 'Тело ответа: ' . $response . '<br />';
                }

                $this->sendEmailError('core.sendSMS', $message);

                return false;
            }
        }

        public function authUser($phone) {
            $user = $this->getUser($phone);

            if ($user) {
                $_SESSION[$this->session_prefix . 'user']       = $user;
                $_SESSION[$this->session_prefix . 'authorized'] = true;

                if (
                    isset($_SESSION[$this->session_prefix . 'checkout.step.1.phone']) and
                    isset($_SESSION[$this->session_prefix . 'user']['phone'])
                ) {
                    $_SESSION[$this->session_prefix . 'checkout.step.1.phone'] = $_SESSION[$this->session_prefix . 'user']['phone'];
                }

                if (
                    isset($_SESSION[$this->session_prefix . 'checkout.step.1.birthday']) and
                    isset($_SESSION[$this->session_prefix . 'user']['birthday'])
                ) {
                    $_SESSION[$this->session_prefix . 'checkout.step.1.birthday'] = $_SESSION[$this->session_prefix . 'user']['birthday'];
                }

                return true;
            } else {
                return false;
            }
        }

        public function getUser($phone) {
            $request_data = array(
                'phone' => $phone
            );

            $response = $this->getServerData('coreCustomerCheck', 'customerCheck', $request_data);

            if ($response['result'] == 'success' and !empty($response['response']['id'])) {
                $request_data = array(
                    "id" => $response['response']['id']
                );

                $response = $this->getServerData('coreCustomerGet', 'customerGet', $request_data);

                if ($response['result'] == 'success') {
                    $user = $response['response'];

                    return $user;
                }
            }

            return false;
        }

        /**
         * @param string $phone
         * @param string $name
         *
         * @return bool|mirSushiUsers
         */
        public function createUser($phone, $name) {
            $user_id = false;

            /** @var mirSushiCities $city */
            $city = $this->modx->getObject('mirSushiCities', array(
                'context_key' => $this->modx->context->key
            ));

            $request_data = array(
                'phone'  => $phone,
                'name'   => $name,
                'cityId' => $city->code
            );

            $response = $this->getServerData('coreCustomerCreate', 'customerCreate', $request_data);

            if ($response['result'] == 'success' and !empty($response['response']['id'])) {
                $this->authUser($phone);

                $user_id = $response['response']['id'];
            }

            return $user_id;
        }

        /**
         * @return string
         */
        public static function generateProtectionCode() {
            $length = 4;

            $code = '';

            $symbols = [
                '1', '2', '3', '4', '5', '6',
                '7', '8', '9', '0'
            ];

            for ($i = 0; $i < $length; $i++) {
                $index = mt_rand(0, sizeof($symbols) - 1);

                $code .= $symbols[$index];
            }

            return $code;
        }

        /**
         * @param string $phone
         *
         * @return string
         */
        public static function preparePhone($phone) {
            $phone = preg_replace('/[^0-9]/', '', $phone);
            $phone = substr_replace($phone, '', 0, 1);

            return $phone;
        }

        /**
         * @param string $string
         *
         * @return string
         */
        public static function prepareString($string) {
            $string = strip_tags($string);

            return $string;
        }

        /**
         * @param string $string
         *
         * @return string
         */
        public static function prepareDate($string) {
            if (preg_match('/[0-9]{2}\.[0-9]{2}\.[0-9]{4}/', $string)) {
                $string = explode('.', $string);
                $string = $string[2] . '-' . $string[1] . '-' . $string[0];
            }

            if (!preg_match('/[0-9]{4}\-[0-9]{2}\-[0-9]{2}/', $string)) {
                $string = '';
            }

            return $string;
        }

        /**
         * @param integer $city_id
         */
        public function removeCity($city_id) {
            $this->removeWorkingTime($city_id);
            $this->removeStreets($city_id);

            $resources = array();
            /** @var mirSushiCategories[] $collection */
            $collection = $this->modx->getIterator('mirSushiCategories', array(
                'city_id' => $city_id
            ));

            foreach ($collection as $item) {
                $resources[] = $item->id;
            }

            $this->removeCategories($resources);

            $resources = array();
            /** @var mirSushiProducts[] $collection */
            $collection = $this->modx->getIterator('mirSushiProducts', array(
                'category_id:IN' => $resources
            ));

            foreach ($collection as $item) {
                $resources[] = $item->id;
            }

            $this->removeProducts($resources);

            $this->modx->removeObject('mirSushiCities', $city_id);
        }

        /**
         * @param integer $city_id
         */
        public function removeStreets($city_id) {
            $this->modx->removeCollection('mirSushiStreets', array(
                'city_id' => $city_id
            ));
        }

        /**
         * @param integer $city_id
         */
        public function removeWorkingTime($city_id) {
            $this->modx->removeCollection('mirSushiWorkingTimes', array(
                'city_id' => $city_id
            ));
        }

        /**
         * @param array $resource_ids
         */
        public function removeCategories($resource_ids) {
            /** @var mirSushiCategories[] $categories */
            $categories = $this->modx->getCollection('mirSushiCategories', array(
                'resource_id:IN' => $resource_ids
            ));

            $this->modx->removeCollection('mirSushiCategories', array(
                'resource_id:IN' => $resource_ids
            ));

            $categories_ids = array();

            foreach ($categories as $category) {
                $categories_ids[] = $category->id;
            }

            $this->modx->removeCollection('mirSushiProducts', array(
                'category_id:IN' => $categories_ids
            ));
        }

        /**
         * @param array $resource_ids
         */
        public function removeProducts($resource_ids) {
            $this->modx->removeCollection('mirSushiProducts', array(
                'resource_id:IN' => $resource_ids
            ));
        }

        /**
         * @param string $code
         *
         * @return array|bool
         */
        public function getProductByCode($code, $context) {
            $q = $this->modx->newQuery('modResource');
            $q->innerJoin('mirSushiProducts', 'mirSushiProducts', 'mirSushiProducts.resource_id = modResource.id');
            $q->where(array(
                'mirSushiProducts.code'        => $code,
                'mirSushiProducts.context_key' => $context
            ));
            $q->limit(1);

            /** @var modResource $resource */
            $resource = $this->modx->getObject('modResource', $q);

            if ($resource) {
                $tvs = $this->modx->runSnippet('snptGetResourceTvs', array(
                    'resource_id' => $resource->id,
                    'prefix'      => 'tv'
                ));

                $product = $resource->toArray();

                $product = array_merge($product, $tvs);

                return $product;
            }

            return false;
        }

        public function prepareCartRequestData($phone = false, $birthday = false) {
            $request_data = array(
                'customer'  => array(
                    'id'       => '',
                    'phone'    => '',
                    'birthday' => ''
                ),
                'cityId'    => '',
                'products'  => array(),
                'additions' => array(),
                'cutlery'   => array(),
                'shipping'  => array(),
                'payment'   => array(),
            );

            // Информация о пользователе
            if (isset($_SESSION[$this->session_prefix . 'authorized']) and $_SESSION[$this->session_prefix . 'authorized']) {
                $request_data['customer'] = array(
                    'id' => $_SESSION[$this->session_prefix . 'user']['id']
                );
            }

            if ($phone !== false) {
                $request_data['customer']['phone'] = self::preparePhone($phone);
            } else if (!empty($_SESSION[$this->session_prefix . 'checkout.step.1.phone'])) {
                $request_data['customer']['phone'] = self::preparePhone($_SESSION[$this->session_prefix . 'checkout.step.1.phone']);
            }

            if ($birthday !== false) {
                $request_data['customer']['birthday'] = self::prepareDate($birthday);
            } else if (!empty($_SESSION[$this->session_prefix . 'checkout.step.1.birthday'])) {
                $birthday = $_SESSION[$this->session_prefix . 'checkout.step.1.birthday'];
                $birthday = self::prepareDate($birthday);

                $request_data['customer']['birthday'] = $birthday;
            }

            // Город
            /** @var mirSushiCities $city */
            $city = $this->modx->getObject('mirSushiCities', array(
                'context_key' => $this->modx->context->key
            ));

            $request_data['cityId'] = $city->code;

            // Товары
            $request_data['products'] = array();

            foreach ($this->getCartProducts() as $product_code => $item) {
                $request_data['products'][] = array(
                    'id'         => $product_code,
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                    'total'      => $item['total'],
                    'actionId'   => $item['action_id'],
                    'actionType' => $item['action_type']
                );
            }

            // Подарки

            foreach ($this->getCartGifts() as $product_code => $item) {
                $request_data['products'][] = array(
                    'id'         => $product_code,
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                    'total'      => $item['total'],
                    'actionId'   => $item['action_id'],
                    'actionType' => $item['action_type']
                );
            }

            // Топинги
            $request_data['additions'] = array();

            foreach ($this->getCartAddition() as $product_code => $item) {
                $freeware_id = isset($_SESSION[$this->session_prefix . 'available_addition'][$product_code])
                    ? $_SESSION[$this->session_prefix . 'available_addition'][$product_code]['freewareId']
                    : '';

                $quantity = isset($_SESSION[$this->session_prefix . 'available_addition'][$product_code])
                    ? $_SESSION[$this->session_prefix . 'available_addition'][$product_code]['quantityMax']
                    : 0;

                $quantity += $item['quantity'];

                $request_data['additions'][] = array(
                    'freewareId' => $freeware_id,
                    'paywareId'  => $product_code,
                    'quantity'   => $quantity
                );
            }

            // Приборы
            $request_data['cutlery'] = array();

            foreach ($this->getCartFlatware() as $product_code => $item) {
                $freeware_id = isset($_SESSION[$this->session_prefix . 'available_flatware'][$product_code])
                    ? $_SESSION[$this->session_prefix . 'available_flatware'][$product_code]['freewareId']
                    : '';

                $quantity = isset($_SESSION[$this->session_prefix . 'available_flatware'][$product_code])
                    ? $_SESSION[$this->session_prefix . 'available_flatware'][$product_code]['quantityMax']
                    : 0;

                $quantity += $item['quantity'];

                $request_data['cutlery'][] = array(
                    'paywareId'  => $product_code,
                    'freewareId' => $freeware_id,
                    'quantity'   => $quantity
                );
            }

            // Доставка
            $request_data['shipping'] = array();

            // Оплата
            $request_data['payment'] = array();

            return $request_data;
        }

        public function orderComplete() {
            $this->clearCart();

            unset($_SESSION[$this->session_prefix . 'checkout.step.1.bonus']);
            unset($_SESSION[$this->session_prefix . 'checkout.step.1.discount']);
            unset($_SESSION[$this->session_prefix . 'checkout.step.1.phone']);
            unset($_SESSION[$this->session_prefix . 'checkout.step.1.birthday']);
            unset($_SESSION[$this->session_prefix . 'checkout.step.1.validate']);

            unset($_SESSION[$this->session_prefix . 'checkout.step.2.validate']);

            unset($_SESSION[$this->session_prefix . 'order_id']);
            unset($_SESSION[$this->session_prefix . 'order_email']);
        }

        /**
         * @param array $json
         *
         * @return bool
         */
        public static function checkApiRequestFormat($json = array()) {
            if (
                empty($json['requestId']) or
                empty($json['sourceId']) or
                empty($json['requestName']) or
                empty($json['request'])
            ) {
                return false;
            } else {
                return true;
            }
        }

        public function checkApiRequestSource($source_id = '') {
            /** @var modContextSetting $sources */
            $sources = $this->modx->getObject('modContextSetting', array(
                'context_key' => 'api',
                'key'         => 'sources_1c'
            ));

            if (empty($sources->value)) {
                return false;
            }
            $sources = explode(',', $sources->value);

            /** @var array $sources */

            if (!is_array($sources)) {
                $sources = array($sources);
            }

            $_sources = array();

            foreach ($sources as $source) {
                $_sources[] = trim($source);
            }

            $sources = $_sources;

            if (!in_array($source_id, $sources)) {
                return false;
            }

            return true;
        }

        /**
         * @param string      $method
         * @param string      $error_text
         * @param string|bool $request_id
         *
         * @return string
         */
        public function exitWithApiError($method = '', $error_text = '', $request_id = false) {
//            /** @var modContextSetting $admin_email */
//            $admin_email = $this->modx->getObject('modContextSetting', array(
//                'context_key' => 'web',
//                'key'         => 'admin_1c_email'
//            ));
//
//            if ($admin_email) {
//                $subject     = '1C api error';
//                $admin_email = $admin_email->value;
//
//                if (!isset($this->modx->mail) || !is_object($this->modx->mail)) {
//                    $this->modx->getService('mail', 'mail.modPHPMailer');
//                }
//
//                $this->modx->mail->set(modMail::MAIL_FROM, 'noreply@' . DEFAULT_DOMAIN);
//                $this->modx->mail->set(modMail::MAIL_FROM_NAME, $this->modx->getOption('site_name'));
//                $this->modx->mail->setHTML(true);
//                $this->modx->mail->set(modMail::MAIL_SUBJECT, trim($subject));
//                $this->modx->mail->set(modMail::MAIL_BODY, $body);
//                $this->modx->mail->address('to', trim($admin_email));
//
//                if ($attach) {
//                    $this->modx->mail->attach($attach);
//                }
//
//                if (!$this->modx->mail->send()) {
//                    $this->modx->log(modX::LOG_LEVEL_ERROR, 'An error occurred while trying to send the email: ' . $this->modx->mail->mailer->ErrorInfo);
//                }
//                $this->modx->mail->reset();
//            }
//
//            die();

            return json_encode(array(
                'requestId' => $request_id,
                'result'    => 'error',
                'error'     => $error_text
            ));
        }

        public function sendEmailError($method, $details) {
            $from    = $this->modx->getOption('emailsender');
            $subject = 'Ошибка при выполнении ' . $method;

            $recipients = $this->modx->getOption('it_notify');
            $recipients = explode(',', $recipients);

            if (!is_array($recipients)) {
                $recipients = array($recipients);
            }

            $headers = 'From: ' . $from . "\r\n" .
                'Content-Type: text/html; charset=utf-8' . "\r\n" .
                'MIME-Version: 1.0' . "\r\n" .
                'X-Mailer: PHP/' . phpversion();

            foreach ($recipients as $recipient) {
                if (!mail($recipient, $subject, $details, $headers)) {
                    return false;
                }
            }

            return true;
        }

        public function processYKResult($yk_config) {
            if ($this->_checkYKSign($yk_config, $_POST)) {
                if (!empty($_POST['orderNumber'])) {
                    $request_data = array(
                        'orderId'     => $_POST['orderNumber'],
                        'payed'       => true,
                        'paymentInfo' => $_POST
                    );

                    if (!empty($_POST['paymentDatetime'])) {
                        $this->getServerData('core.processYKResult', 'cartPayed', $request_data);
                    }

                    $this->_sendYKCode($yk_config, $_POST, 0);
                } else {
                    $this->_sendYKCode($yk_config, $_POST, 200);
                }
            } else {
                $this->_sendYKCode($yk_config, $_POST, 1);
            }
        }

        private function _checkYKSign($config, $params) {
            $string = $params['action'] . ';' . $params['orderSumAmount'] . ';' . $params['orderSumCurrencyPaycash'] . ';' . $params['orderSumBankPaycash'] . ';' . $params['shopId'] . ';' . $params['invoiceId'] . ';' . $params['customerNumber'] . ';' . $config['password'];
            $md5    = strtoupper(md5($string));

            return ($params['md5'] == $md5);
        }

        private function _sendYKCode($config, $params, $code) {
            header("Content-type: text/xml; charset=utf-8");
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
			<' . $params['action'] . 'Response performedDatetime="' . date("c") . '" code="' . $code . '" invoiceId="' . $params['invoiceId'] . '" shopId="' . $config['shopid'] . '"/>';
            echo $xml;
        }

        /**
         * @param string $orderNumber
         *
         * @return string
         */
        public static function prepareOrderNum($orderNumber) {
            $from = array(
                'СС',
                'ТО',
                'ТЮМ',
                'КРД',
                'КРС'
            );
            $to = array(
                'CC',
                'TO',
                'TUM',
                'KRD',
                'KRS'
            );
            return str_replace($from, $to, $orderNumber);
        }

        /**
         * @param string $orderNumber
         *
         * @return string
         */
        public static function prepareOrderNum1C($orderNumber) {
            $to = array(
                'СС',
                'ТО',
                'ТЮМ',
                'КРД',
                'КРС'
            );
            $from = array(
                'CC',
                'TO',
                'TUM',
                'KRD',
                'KRS'
            );
            return str_replace($from, $to, $orderNumber);
        }

        /**
         * @for: Sberbank
         *
         * @param string $orderNumber
         *
         * @return string
         */
        public function getOrderIdByNumber($orderNumber) {
            if($order = $this->modx->getObject('Order', array('ordernumber' => $orderNumber))) return $order->get('orderid');
            else return false;
        }
        public function getOrderAmountByNumber($orderNumber) {
            if($order = $this->modx->getObject('Order', array('ordernumber' => $orderNumber))) return $order->get('amount');
            else return false;
        }
        /**
         * @from: Sberbank
         *
         * @param string $orderId
         *
         * @return string
         */
        public function getOrderNumberById($orderId) {
            if($order = $this->modx->getObject('Order', array('orderid' => $orderId))) return $order->get('ordernumber');
            else return false;
        }

    }
