<?php

namespace Bro\AppBundle\Controller\Manage;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Bro\AppBundle\Controller\Manage\ManageController;
use Bro\ApiBundle\Controller\YandexApiController;

use Bro\AppBundle\Entity\AjaxResponse;

use Bro\AppBundle\Entity\Strategy;
use Bro\AppBundle\Entity\Phrase;

class PhrasesController extends ManageController {

	private $AjaxResponse;

	function __construct(&$AjaxResponse=false){
	  $this->AjaxResponse=AjaxResponse::getInstance();
	}

	/*	public function indexAction($yandex_login=false, $yandex_client=false) {
		$side$this->renderManageSidebar($yandex_login, $yandex_client);
	}*/


    public function addAction(Request $Request, $ad_group_id){

        $EM=$this->getDoctrine()->getManager();
        $AdGroup=$EM->getRepository('BroAppBundle:AdGroup')->findOneById($ad_group_id);
        $User=$this->getUser();
        $YandexApi = $this->get('yandex_api5');

        $new_phrases_list=explode(',', $Request->request->get('phrases'));
        $new_phrases=[];

        if(count($new_phrases_list)){

            if($AdGroup){
                $YandexApi->setToken5($AdGroup->getYandexLogin()->getToken(), $AdGroup->getYandexLogin());

                if($Request->getMethod() == 'POST') {

                    /*$bannersIDs=[];
                    foreach($AdGroup->getBanners() as $Banner){
                    $bannersIDs[]=$Banner->getBannerID();
                    }*/

                    if($EM->getRepository('BroAppBundle:AdGroup')->checkUserAcces($AdGroup->getId(), $this->getUser())){

                        /* $banners=$YandexApi->sendRequest('GetBanners', ['BannerIDS'=>$bannersIDs,
                                                    'FieldsNames'=>['BannerID', 'CampaignID', 'Title', 'Text', 'Href', 'ContactInfo', 'Geo'],
                                                    'GetPhrases'=>'Yes'
                                                   ], 'data');*/


                        //if(!$YandexApi->hasApiError()){

                        /*foreach($banners as $key=>$banner){
                        foreach($new_phrases_list as $phrase){
                        $new_phrase=['Phrase'=>$phrase,
                        'Price'=>1,
                        'ContextPrice'=>1];

                        $banners[$key]['Phrases'][]=$new_phrase;
                        }
                        }*/

                        foreach($new_phrases_list as $phrase){
                            $new_phrases[]=[
                                'Keyword'=>$phrase,
                                'AdGroupId'=>$AdGroup->getAdGroupID(),
                                'Bid'=>30*1000000,
                                'ContextBid'=>30*1000000
                            ];
                        }
//dump($new_phrases);
                        //$YandexApi->sendRequest('CreateOrUpdateBanners', $banners, 'data');
                        $YandexApi->send5Request('keywords', 'add', ['Keywords'=>$new_phrases], [], 'AddResults');

                        if(!$YandexApi->hasApiError()){

                            foreach($AdGroup->getBanners() as $Banner){
                                $Banner->setStatusBannerModerate('Pending');
                            }

                            /*$banners=$YandexApi->sendRequest('GetBanners', ['BannerIDS'=>$bannersIDs,
                                                            'FieldsNames'=>['BannerID'],
                                                            'GetPhrases'=>'WithPrices'
                                                           ], 'data');*/

                            $request_data=['SelectionCriteria'=>['AdGroupIds'=>[$AdGroup->getAdGroupID()]]];
                            $request_data=array_merge($request_data, $EM->getRepository('BroAppBundle:Phrase')->getStdYandexApiParams());
                            $phrases=$YandexApi->send5Request('keywords', 'get', $request_data, [], 'Keywords');
                            //dump($phrases);
                            if(!$YandexApi->hasApiError()){

                                /*foreach($banners[0]['Phrases'] as $phrase){
                                    if(!isset($AdGroup->getPhrases()[$phrase['PhraseID']])){
                                        $NewPhrase=new Phrase();
                                        $NewPhrase->fill($phrase)
                                        ->setUser($AdGroup->getUser())
                                        ->setYandexLogin($AdGroup->getYandexLogin())
                                        ->setCampain($AdGroup->getCampain())
                                        ->setAdGroup($AdGroup)
                                        ->setStrategy($AdGroup->getStrategy())
                                        ->setMaxPrice($AdGroup->getMaxPrice());

                                        $AdGroup->addPhrase($NewPhrase);
                                    }
                                }*/

                                //dump($phrases);

                                foreach($phrases as $phrase){
                                    if(!isset($AdGroup->getPhrases()[$phrase['Id']])){
                                        $NewPhrase=new Phrase();
                                        $NewPhrase->fill($phrase)

                                            ->setClicks(0)
                                            ->setShows(0)
                                            ->setMinPrice(0)
                                            ->setStatusPaused('NOT_SET')

                                            ->setUser($AdGroup->getUser())
                                            ->setYandexLogin($AdGroup->getYandexLogin())
                                            ->setCampain($AdGroup->getCampain())
                                            ->setAdGroup($AdGroup)
                                            ->setStrategy($AdGroup->getStrategy())
                                            ->setMaxPrice($AdGroup->getMaxPrice());

                                        $AdGroup->addPhrase($NewPhrase);
                                    }
                                }

                                $EM->flush();
                                $this->AjaxResponse->setData(['url'=>$this->generateUrl('manage_ad_group_prices_edit', ['ad_group_id'=>$AdGroup->getId()])], 'json', 'ok');

                            } else {
                                $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                            }

                        } else {
                            $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                        }

                       /* } else {
                        $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                        }*/

                    } else {
                    $this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
                    }
                }
            } else {
                $this->AjaxResponse->addError('p_013', 'Группа объявлений для фраз не найдена');
            }

        } else {
            $this->AjaxResponse->addError('p_014', 'Нет фраз для добавления');
        }


        if($Request->isXmlHttpRequest()){
            return $this->AjaxResponse->getResponse();
        }

        return new Response();
    }


    public function editAction(Request $Request, $phrase_id){

        $EM=$this->getDoctrine()->getManager();
        $Phrase=$EM->getRepository('BroAppBundle:Phrase')->findOneById($phrase_id);
        $Banners=$Phrase->getAdGroup()->getBanners();
        $YandexApi = $this->get('yandex_api5');



        if($Phrase){
            $YandexApi->setToken5($Phrase->getYandexLogin()->getToken(), $Phrase->getYandexLogin());

            $bannersIDs=[];
            foreach($Banners as $Banner){
                $bannersIDs[]=$Banner->getBannerID();
            }

            if($Request->getMethod() == 'POST') {

                if($EM->getRepository('BroAppBundle:Phrase')->checkUserAcces($phrase_id, $this->getUser())){

                    /* $banners=$YandexApi->sendRequest('GetBanners', ['BannerIDS'=>$bannersIDs,
                                                          'FieldsNames'=>['BannerID', 'CampaignID', 'Title', 'Text', 'Href', 'ContactInfo', 'Geo'],
                                                          'GetPhrases'=>'Yes'
                                                         ], 'data');*/



                    //if(!$YandexApi->hasApiError()){
                        /*foreach($banners as $key=>$banner){
                            foreach($banner['Phrases'] as $k=>$phrase){
                                if($phrase['PhraseID']==$Phrase->getPhraseID()){
                                    $banners[$key]['Phrases'][$k]['Phrase']=$Phrase->getPhrase();
                                    break;
                                }
                            }
                        }

                        $YandexApi->sendRequest('CreateOrUpdateBanners', $banners, 'data');*/

                        $request_data=[
                            'Keywords'=>[
                                ['Id'=>$Phrase->getPhraseID(), 'Keyword'=>$Request->request->get('phrase') ]
                            ]
                        ];
                        $YandexApi->send5Request('keywords', 'update', $request_data, [], 'UpdateResults');

                        if(!$YandexApi->hasApiError()){

                            $Phrase->setPhrase($Request->request->get('phrase'));

                            foreach($Banners as $Banner){
                                $Banner->setStatusBannerModerate('Pending');
                            }

                            $EM->flush();
                            $this->AjaxResponse->setStatus('ok');
                        } else {
                            $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                        }

                   /* } else {
                        $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                    }*/

                } else {
                    $this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
                }
            }
        } else {
            $this->AjaxResponse->addError('p_013', 'Редактируемая фраза не найдена');
        }


        if($Request->isXmlHttpRequest()){
            return $this->AjaxResponse->getResponse();
        }

        return new Response();
    }


    public function deleteAction(Request $Request, $phrase_id){

        $EM=$this->getDoctrine()->getManager();
        $Phrase=$EM->getRepository('BroAppBundle:Phrase')->findOneById($phrase_id);
        $AdGroup=$Phrase->getAdGroup();
        //$Banners=$Phrase->getAdGroup()->getBanners();
        $YandexApi = $this->get('yandex_api5');

        $n_phrases=0;

        if($Phrase){
            $YandexApi->setToken5($Phrase->getYandexLogin()->getToken(), $Phrase->getYandexLogin());

            /*$bannersIDs=[];
            foreach($Banners as $Banner){
                $bannersIDs[]=$Banner->getBannerID();
            }*/

            if($Request->getMethod() == 'POST') {

                if($EM->getRepository('BroAppBundle:Phrase')->checkUserAcces($phrase_id, $this->getUser())){

                    /*$banners=$YandexApi->sendRequest('GetBanners', ['BannerIDS'=>$bannersIDs,
                                                      'FieldsNames'=>['BannerID', 'CampaignID', 'Title', 'Text', 'Href', 'ContactInfo', 'Geo'],
                                                      'GetPhrases'=>'Yes'
                                                     ], 'data');*/

                   // if(!$YandexApi->hasApiError()){
                        /*foreach($banners as $key=>$banner){

                            $n_phrases=count($banner['Phrases']);

                            foreach($banner['Phrases'] as $k=>$phrase){
                                if($phrase['PhraseID']==$Phrase->getPhraseID()){
                                    unset($banners[$key]['Phrases'][$k]);
                                    $banners[$key]['Phrases']=array_values($banners[$key]['Phrases']);
                                }
                            }
                        }

                        if($n_phrases>1){

                            $YandexApi->sendRequest('CreateOrUpdateBanners', $banners, 'data');
                            if(!$YandexApi->hasApiError()){

                                $EM->remove($Phrase);

                                $EM->flush();
                                $this->AjaxResponse->setStatus('ok');
                            } else {
                                $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                            }

                        } else {
                            $this->AjaxResponse->addError('p_045', 'В группе должна остаться хотя бы одна фраза');
                        }*/
                        if(count($AdGroup->getPhrases())>1){
                            $request_data=[
                                'SelectionCriteria'=>['Ids'=>[$Phrase->getPhraseID()]]
                            ];
                            $YandexApi->send5Request('keywords', 'delete', $request_data, [], 'DeleteResults');
                            if(!$YandexApi->hasApiError()){
                                $EM->remove($Phrase);
                                $EM->flush();
                                $this->AjaxResponse->setStatus('ok');
                            } else {
                                $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                            }
                        } else {
                            $this->AjaxResponse->addError('p_045', 'Нельзя удалить последнюю фразу группы');
                        }


                    /*} else {
                        $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                    }*/

                } else {
                    $this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
                }
            }
        } else {
            $this->AjaxResponse->addError('p_013', 'Удаляемая фраза не найдена');
        }


        if($Request->isXmlHttpRequest()){
            return $this->AjaxResponse->getResponse();
        }

        return new Response();
    }



    //**************************************************
    //******************** ДЕЙСТВИЯ ********************
    //**************************************************
    public function editPhraseStrategyAction($phrase_id, $strategy_id=false, $render_response=false){
    $Request=$this->getRequest();
        $EM=$this->getDoctrine()->getManager();
        $Phrases=$EM->getRepository('BroAppBundle:Phrase')->findById($phrase_id);
    if(!$strategy_id){
      $strategy_id=$Request->request->get('strategy_id');
    }
    $Strategy=$EM->getRepository('BroAppBundle:Strategy')->findOneById($strategy_id);


        if ($Request->getMethod() == 'POST') {

            if($Strategy){
              foreach($Phrases as &$Phrase){
                if($EM->getRepository('BroAppBundle:Phrase')->checkUserAcces($Phrase->getId(), $this->getUser())){
            $Phrase->setStrategy($Strategy);

                } else {
                    $this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
            break;
                }
        }
        unset($Phrase);

        //Если нет ошибок отправляем в базу и отдаем нужный ответ
        if(!$this->AjaxResponse->getHasErrors()){
          $EM->flush();

          //Если нужно вернуть рендер
          if($render_response){
            /*$referer_params = $this->get('router')->match(str_replace('http://'.$_SERVER['HTTP_HOST'], '', $_SERVER['HTTP_REFERER']));
            $this->AjaxResponse->setData(array('workflow'=>$this->campainAction((isset($referer_params['filter'])?$referer_params['filter']:false),
                                                                                  (isset($referer_params['yandex_login'])?$referer_params['yandex_login']:false),
                                                                                  (isset($referer_params['yandex_client'])?$referer_params['yandex_client']:false),
                                                                                  (isset($referer_params['campain_id'])?$referer_params['campain_id']:false),
                                                                                  (isset($referer_params['banner_id'])?$referer_params['banner_id']:false))), 'html', 'ok');*/
            $this->AjaxResponse->setData(array('content'=>$this->forwardBack(true, true)->getContent()), 'html', 'ok');

          } else {
            $this->AjaxResponse->setStatus('ok');
          }
        }

            } else {
        $this->AjaxResponse->addError('mp_001', 'Неизвестная стратегия');
            }

            return $this->AjaxResponse->getResponse();
        }

     return new Response();

    }

    public function editPhraseMaxPriceAction($phrase_id, $max_price=false, $render_response=false){
    $Request=$this->getRequest();
        $EM=$this->getDoctrine()->getManager();
    if($max_price===false){
      $max_price=$Request->request->get('max_price');
    }
        $Phrases=$EM->getRepository('BroAppBundle:Phrase')->findById($phrase_id);

        if ($Request->getMethod() == 'POST') {
      foreach($Phrases as &$Phrase){
            if($EM->getRepository('BroAppBundle:Phrase')->checkUserAcces($Phrase->getId(), $this->getUser())){
          $Phrase->setMaxPrice($max_price);

            } else {
                $this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
          break;
            }
      }
      unset($Phrase);

      if(!$this->AjaxResponse->getHasErrors()){
        $EM->flush();

        //Если нужно вернуть рендер
        if($render_response){
          /*$referer_params = $this->get('router')->match(str_replace('http://'.$_SERVER['HTTP_HOST'], '', $_SERVER['HTTP_REFERER']));
          $this->AjaxResponse->setData(array('workflow'=>$this->campainAction((isset($referer_params['filter'])?$referer_params['filter']:false),
                                                                              (isset($referer_params['yandex_login'])?$referer_params['yandex_login']:false),
                                                                              (isset($referer_params['yandex_client'])?$referer_params['yandex_client']:false),
                                                                              (isset($referer_params['campain_id'])?$referer_params['campain_id']:false),
                                                                              (isset($referer_params['banner_id'])?$referer_params['banner_id']:false))), 'html', 'ok');*/

          //Раньше было так, поменял для редактирования объявления
          //$this->AjaxResponse->setData(array('workflow'=>$this->forwardBack()->getContent()), 'html', 'ok');

          $this->AjaxResponse->setData(array('content'=>$this->forwardBack(true, true)->getContent()), 'html', 'ok');

        } else {
          $this->AjaxResponse->setStatus('ok');
        }

      }

            return $this->AjaxResponse->getResponse();
        }

     return new Response();

    }


    //**************************************************
    //****************** API ДЕЙСТВИЯ ******************
    //**************************************************
    public function controllPhraseAction($phrase_id, $action, $render_response=false){
        $YandexApi = $this->get('yandex_api5');
        $EM=$this->getDoctrine()->getManager();

        $max_items=10000;
        $phrase_ids=array();

        if(!is_array($phrase_id)){
            $phrase_id=array($phrase_id);
        }


        if($action=='Suspend'||$action=='Stop'){
            $action='suspend';
        } else if($action=='Resume'){
            $action='resume';
        }



        if($action=='suspend'||$action=='resume'){
            $Phrases=$EM->getRepository('BroAppBundle:Phrase')->findWithYandexLogin($phrase_id, true, true);

            if(count($Phrases)>0){

                reset($Phrases);
                $YandexLogin=$Phrases[key($Phrases)]->getYandexLogin();
                $YandexApi->setToken5($YandexLogin->getToken(), $YandexLogin);


                //По хорошему нужно бы сделать проверку на последнюю фразу в банере
                //но я сделаю это на уровне сбора id  в js
                foreach($Phrases as $key=>&$Phrase){
                    $phrase_ids[]=$Phrase->getPhraseID();

                    if($action=='suspend'){
                        $Phrase->setStatusPaused('Yes')
                        ->setAutoStopped(false);
                    } else if($action=='resume'){
                        $Phrase->setStatusPaused('No')
                        ->setAutoStopped(false);
                    }
                }
                unset($Phrase);

                if($EM->getRepository('BroAppBundle:Phrase')->checkPackUserAcces($phrase_id, $this->getUser())){

                    $phrase_ids=array_chunk($phrase_ids, $max_items);
                    $errors=[];
                    $bad_ad_groups=[];
                    foreach($phrase_ids as $phrase_id_pack){
                    /*  $result=$YandexApi->send5Request('Keyword', array('Action'=>$action,
                                             'Login'=>$YandexLogin->getLogin(),
                                             'KeywordIDS'=>$phrase_id_pack), 'data');*/

                        $request_data=[
                            'SelectionCriteria'=>['Ids'=>$phrase_id_pack]
                        ];
                        $results=$YandexApi->send5Request('keywords', $action, $request_data, [], ucfirst($action).'Results');

    /*                        if(isset($result['ActionsResult'])){
                            foreach($result['ActionsResult'] as $actionsResult){

                            if(isset($actionsResult['Errors'])){
                                foreach($actionsResult['Errors'] as $error){
                                    $errors[$error['FaultCode']]=$error['FaultString'];
                                }

                                    $EM->detach($Phrases[$actionsResult['KeywordID']]);
                                    $bad_ad_groups[$Phrases[$actionsResult['KeywordID']]->getAdGroup()->getAdGroupID()]=true;
                                }
                            }

                            if($errors){
                                $bad_ad_groups_list='';
                                foreach($bad_ad_groups as $key=>$bad_ad_group){
                                    $bad_ad_groups_list.=($key.',');
                                }

                                foreach($errors as $key=>$error){
                                    $error='Для групп объявлений <strong>'.trim($bad_ad_groups_list, ',').'</strong> остановка фраз не произведена. '.$error;

                                    $this->AjaxResponse->addWarning('ya_'.$key, $error);
                                }
                            }

                        }*/

                        if(!$YandexApi->hasApiError()){

                            if(count($results)>0){
                                //dump($results);
                                foreach($results as $result){
                                    if(isset($result['Errors'])){
                                        foreach($result['Errors'] as $error){
                                            $errors[$error['Code']]=$error['Message'];
                                        }

                                        $EM->detach($Phrases[$result['Id']]);
                                        $bad_ad_groups[$Phrases[$result['Id']]->getAdGroup()->getAdGroupID()]=true;
                                    }
                                }
                            }
                            if($errors){
                                $bad_ad_groups_list='';
                                foreach($bad_ad_groups as $key=>$bad_ad_group){
                                    $bad_ad_groups_list.=($key.',');
                                }

                                foreach($errors as $key=>$error){
                                    $error='Для групп объявлений <strong>'.trim($bad_ad_groups_list, ',').'</strong> остановка фраз не произведена. '.$error;

                                    $this->AjaxResponse->addWarning('ya_'.$key, $error);
                                }
                            }

                            $EM->flush();

                            if($render_response){
                                $this->AjaxResponse->setData(array('time'=>$YandexApi->getRequestTime(), 'content'=>$this->forwardBack(true, true)->getContent()), 'html', 'ok');
                            } else {
                                $this->AjaxResponse->setStatus('ok');
                            }

                        } else {
                            $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                        }




                        }


                    }



                } else {
                $this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
                }

        } else {
            $this->AjaxResponse->addError('s_032', 'Это действие недоступно для фраз');
        }

        return $this->AjaxResponse->getResponse();
    }


}
