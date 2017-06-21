<?php
/**
     * User : dimsaf
     * Email: dimsaf@mail.ru
     *
     * @var modX $modx
     */

    /** @var mirSushi $mirSushi */
    if (!$mirSushi = $modx->getService('mirsushi', 'mirSushi', $modx->getOption('mirsushi_core_path', null, $modx->getOption('core_path') . 'components/mirSushi/') . 'model/mirsushi/', $scriptProperties)) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[mirSushi] Could not load mirSushi class in snptGetUser.');

        return;
    }

    $update = $modx->getOption('update', $scriptProperties, false);

    if (isset($_SESSION[$mirSushi->session_prefix . 'authorized']) and $_SESSION[$mirSushi->session_prefix . 'authorized']) {

        $user = $_SESSION[$mirSushi->session_prefix . 'user'];

        if ($update) {
            $user = $mirSushi->getUser($user['phone']);

            if ($user) {
                $_SESSION[$mirSushi->session_prefix . 'user'] = $user;
                $modx->cacheManager->refresh();
            }
        }
    
        $user['photo'] = '';
        if (isset($_SESSION[$mirSushi->session_prefix . 'avatar'])) {
            $user['photo'] = $_SESSION[$mirSushi->session_prefix . 'avatar'];
        }

        $modx->setPlaceholder($mirSushi->session_prefix . 'user.authorized', 1);

        $modx->setPlaceholder($mirSushi->session_prefix . 'user.name', $user['name']);

        $modx->setPlaceholder($mirSushi->session_prefix . 'user.phone', $user['phone']);

        $modx->setPlaceholder($mirSushi->session_prefix . 'user.email', $user['email']);

        $modx->setPlaceholder($mirSushi->session_prefix . 'user.birthday', $user['birthday']);

        $modx->setPlaceholder($mirSushi->session_prefix . 'user.sex', $user['sex']);

        $modx->setPlaceholder($mirSushi->session_prefix . 'user.subscribe', $user['subscribe']);

        $modx->setPlaceholder($mirSushi->session_prefix . 'user.subscribe_email', $user['subscribeEmail']);

        $modx->setPlaceholder($mirSushi->session_prefix . 'user.bonus', $user['amountBonus']);

		$modx->setPlaceholder($mirSushi->session_prefix . 'user.promocode', $user['promocode']);

        $modx->setPlaceholder($mirSushi->session_prefix . 'user.avatar', $user['photo']);

        $modx->setPlaceholder($mirSushi->session_prefix . 'user.hasCurrentOrder', $user['hasCurrentOrder']); 

        // вкладка индивидуальные предложения 
        $indOffersHtml = $endOffer = '';
        $placeholders = array();
        if (!empty($user['indOffers'])) {
            // показываем только уникальные значения
            $indOffersUnique = array_map("unserialize",array_unique(array_map("serialize", $user['indOffers'])));
            foreach($indOffersUnique as $key => $offer) {
                
                // показываем только ДЕЙСТВУЮЩИЕ акции
                $startOfferTimestamp = ($offer['start'] != "") ? $offer['start']-7*60*60 : null;
                $endOfferTimestamp = ($offer['end'] != "") ? $offer['end']-7*60*60 : null;
                
                if (($startOfferTimestamp <= time()) && ($endOfferTimestamp >= time() || empty($endOfferTimestamp))) {
                    $placeholders['text'] = $offer['text'];
                    $placeholders['start'] = date("d.m.Y", $startOfferTimestamp);
                    $placeholders['end'] = (!empty($endOfferTimestamp)) ? date("d.m.Y", $endOfferTimestamp) : 'ПОСТОЯННО!';
                    if (empty($offer['offerType'])) {
                        // тип акции отсутствует - ссылка на меню
                        $placeholders['href'] = "href='menu/'";
                        $placeholders['class'] = "js-ind-offer";
                        $placeholders['data'] = "";
                    } else {
                        $offerType = $offer['offerType'];
                        $offerType = (strpos ($offerType, 'discount') !== false) ? 'discount' : $offerType;
                        
                        // если тип скидка и не один акционный продукт, открыть модалку с выбором продукта
                        if (($offerType == 'discount') && (count($offer['offerProducts']) > 1)) {
                                $placeholders['href'] = "";
                                $placeholders['class'] = "";
                                $indOffersGifts = "";
                                $placeholders['data'] = 'data-toggle="modal" data-target="#action-product-modal"';
                                
                                foreach ($offer['offerProducts'] as $action_product_item) {
                                    $action_product_resource_id = $mirSushi->productCodeToResourceId($action_product_item['id']);
                                    
                                    if ($action_product_resource_id) {
                                        /** @var modResource $action_product_resource */
                                        $action_product_resource = $modx->getObject('modResource', $action_product_resource_id);
                                        
                                        if ($action_product_resource) {
                                            $id    = $action_product_resource->id;
                                            $price = $action_product_item['price'];
                                            
                                            $placeholders1 = $action_product_resource->toArray();
                                            
                                            $tvs = $modx->runSnippet('snptGetResourceTvs', array(
                                                'resource_id' => $action_product_resource->id,
                                                'prefix'      => 'tv'
                                            ));
                                            
                                            $placeholders1 = array_merge($placeholders1, $tvs);
                                            
                                            //$placeholders1['tv-product-price'] = $price;
                                            $indOffersGifts .= $modx->getChunk('chnkSectionCategoryItemsItem', $placeholders1);
                                        }
                                    }
                                }
                                $modx->setPlaceholder($mirSushi->session_prefix . 'user.indOffers.gifts', $indOffersGifts);

                        // если один акционный продукт - кинуть его в корзину
                        } else {
                                $placeholders['href'] = "";
                                $placeholders['class'] = "js-add-to-cart-offer";
                                $id = $offer['offerProducts'][0]['id'];
                                if (!empty($id)) $id =  $mirSushi->productCodeToResourceId($id);
                                $placeholders['data'] = "data-item-id='" . $id . "'";
                        }
                    }

                    $indOffersHtml .= $modx->getChunk('chnkSectionAccountIndividualOfferItem', $placeholders);
                    
                } else unset($indOffersUnique[$key]);
                
            }
        } else {
            $indOffersHtml .= 'Для Вас пока нет индивидуальных предложений.<br><br>';
        }   
        
        $modx->setPlaceholder($mirSushi->session_prefix . 'user.indOffers.html', $indOffersHtml);

        $modx->setPlaceholder($mirSushi->session_prefix . 'user.indOffers.count', count($indOffersUnique));
        
        if (!empty($user['bonusProgress'])) {
            $modx->setPlaceholder($mirSushi->session_prefix . 'user.bonus.progress.from', $user['bonusProgress']['fromPct']);

            $modx->setPlaceholder($mirSushi->session_prefix . 'user.bonus.progress.current', $user['bonusProgress']['currentPct']);

            $modx->setPlaceholder($mirSushi->session_prefix . 'user.bonus.progress.to', $user['bonusProgress']['toPct']);

            $modx->setPlaceholder($mirSushi->session_prefix . 'user.bonus.progress.bar', $user['bonusProgress']['progressBarPct']);

            $modx->setPlaceholder($mirSushi->session_prefix . 'user.bonus.progress.message', $user['bonusProgress']['message']);
        }
    } else {
        $modx->setPlaceholder($mirSushi->session_prefix . 'user.authorized', -1);

        // Если страница аккаунта и пользователь не авторизован
        if ($modx->resource->id == $modx->getOption('account_page')) {
            $modx->sendRedirect($modx->makeUrl($modx->getOption('site_start')));
        }
    }
