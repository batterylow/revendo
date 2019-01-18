<?php

namespace Bro\AppBundle\Controller\Manage;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Bro\AppBundle\Entity\AjaxResponse;

use Bro\AppBundle\Entity\Strategy;
use Bro\AppBundle\Entity\Banner;
use Bro\AppBundle\Entity\AdGroup;
use Bro\AppBundle\Entity\Phrase;


use Bro\AppBundle\Form\Type\Manage\AdGroupType;
use Bro\AppBundle\Form\Type\Manage\AdGroupPricesType;


use Bro\AppBundle\Controller\Manage\ManageController;
use Bro\ApiBundle\Controller\YandexApiController;

class AdGroupsController extends ManageController {

	private $AjaxResponse;

	function __construct(){
		$this->AjaxResponse=AjaxResponse::getInstance();
	}


  public function addAction($campain_id ){
    $Request=$this->getRequest();
    $EM=$this->getDoctrine()->getManager();
    $User = $this->getUser();
    $YandexApi = $this->get('yandex_api');

    $YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findUserRootYandexLoginsWithCampains($User->getId());
    $Campain=$EM->getRepository('BroAppBundle:Campain')->findOneById($campain_id);
    $YandexApi->setToken($Campain->getYandexLogin()->getToken());

    $yandex_login=$Campain->getYandexLogin()->getParentLogin()?$Campain->getYandexLogin()->getParentLogin()->getLogin():$Campain->getYandexLogin()->getLogin();
    $yandex_client=$Campain->getYandexLogin()->getParentLogin()?$Campain->getYandexLogin()->getLogin():false;

    $regions=$YandexApi->sendRequest('GetRegions', [], 'data');
    $geo=false;
    $contactInfo=false;
    $active_regions=false;
    $active_regions_names=['active'=>[], 'excepted'=>[]];

    //Проверяем на единый регион
    if(count($Campain->getAdGroups())){
      foreach($Campain->getAdGroups() as $AdGroup){
        if(!$geo){
          $geo=$AdGroup->getGeo();
        } else if($geo!==$AdGroup->getGeo()){
          $geo=false;
          break;
        }
      }
    } else {
      $geo=$Campain->getGeo();
    }

    if($geo){
      $active_regions=explode(',', $geo);

      //Ищем имена активных регионов
      //не особо эффективно,
      //в построении дерева обход по этому массиву идет опять
      if(is_array($regions)&&is_array($active_regions)){
        foreach($regions as $region){
          if($region['RegionID']&&array_search($region['RegionID'], $active_regions)!==false){
            $active_regions_names['active'][$region['RegionID']]=$region['RegionName'];
          }
          if($region['RegionID']&&array_search('-'.$region['RegionID'], $active_regions)!==false){
            $active_regions_names['excepted'][$region['ParentID']][$region['RegionID']]=$region['RegionName'];
          }
        }
      }
    }

    //Проверяем на единые контакты
    if(count($Campain->getBanners())){
      foreach($Campain->getBanners() as $Banner){
        if(!$contactInfo){
          $contactInfo=$Banner->getContactInfo();
        } else if($contactInfo!==$Banner->getContactInfo()){
          $contactInfo=false;
          break;
        }
      }

      if($contactInfo){
        $contactInfo=json_decode($contactInfo, true);
      }
    } else {
      $contactInfo=json_decode($Campain->getContactInfo(), true);
    }

    $AdGroupForm=$this->createForm(new AdGroupType(false, false, false, $geo, $contactInfo), null, ['validation_groups' => array('add_ad_group')]);

    $first_banner=[];
    $banners=[];
    //рабочее врмя
    $workTime=[['days'=>[0, 1, 2, 3, 4], 'time'=>['from'=>['hours'=>'10', 'minutes'=>'00'],'to'=>['hours'=>'18', 'minutes'=>'00']]]];

    if($Request->getMethod() == 'POST') {

      if($EM->getRepository('BroAppBundle:Campain')->checkUserAcces($Campain->getId(), $this->getUser())){

        $AdGroupForm->handleRequest($Request);

        if($Request->isXmlHttpRequest()){
          if($AdGroupForm->isValid()){

            $NewAdGroup=new AdGroup();
            $NewAdGroup->setUser($User)
                       ->setYandexLogin($Campain->getYandexLogin())
        							 ->setCampain($Campain)
                       ->setStrategy($Campain->getStrategy())
                       ->setMaxprice($Campain->getMaxPrice());

            //Идем по списку объявлений в форме
            foreach($AdGroupForm['Banners']->getData() as $key=>$BannerForm){

              $NewBanner= new Banner();
              $NewBanner->setStatusBannerModerate('Pending')
                        ->SetUser($NewAdGroup->getUser())
                        ->SetYandexLogin($NewAdGroup->getYandexLogin())
        								->SetCampain($NewAdGroup->getCampain())
                        ->SetAdGroup($NewAdGroup);


              $banner=['BannerID'=>0,
                       'AdGroupID'=>0,
                       'CampaignID'=>$Campain->getCampaignID(),
                       'AdGroupName'=>$AdGroupForm['AdGroupName']->getData(),
                       'Title'=>$BannerForm['Title'],
                       'Text'=>$BannerForm['Text'],
                       'Href'=>$BannerForm['Href'],
                       'Type'=>$BannerForm['Type']?'Mobile':'Desktop',
                       'Geo'=>implode(',', json_decode($AdGroupForm['Geo']->getData(), true)),
                       'AdGroupMobileBidAdjustment'=>$AdGroupForm['AdGroupMobileBidAdjustment']->getData()
                      ];

              //Контакты
              if($BannerForm['ContactInfo']['Status']){

                $banner['ContactInfo']=$BannerForm['ContactInfo'];
                unset($banner['ContactInfo']['Status']);

                $banner['ContactInfo']['ContactPerson']=$banner['ContactInfo']['ContactPerson']?$banner['ContactInfo']['ContactPerson']:null;
                $banner['ContactInfo']['Street']=$banner['ContactInfo']['Street']?$banner['ContactInfo']['Street']:null;
                $banner['ContactInfo']['House']=$banner['ContactInfo']['House']?$banner['ContactInfo']['House']:null;
                $banner['ContactInfo']['Build']=$banner['ContactInfo']['Build']?$banner['ContactInfo']['Build']:null;
                $banner['ContactInfo']['Apart']=$banner['ContactInfo']['Apart']?$banner['ContactInfo']['Apart']:null;
                $banner['ContactInfo']['PhoneExt']=$banner['ContactInfo']['PhoneExt']?$banner['ContactInfo']['PhoneExt']:'';
                $banner['ContactInfo']['IMLogin']=$banner['ContactInfo']['IMLogin']?$banner['ContactInfo']['IMLogin']:'';
                $banner['ContactInfo']['ExtraMessage']=$banner['ContactInfo']['ExtraMessage']?$banner['ContactInfo']['ExtraMessage']:null;
                $banner['ContactInfo']['ContactEmail']=$banner['ContactInfo']['ContactEmail']?$banner['ContactInfo']['ContactEmail']:null;
                $banner['ContactInfo']['OGRN']=$banner['ContactInfo']['OGRN']?$banner['ContactInfo']['OGRN']:null;

                $banner['ContactInfo']['CountryCode']='+'.$banner['ContactInfo']['CountryCode'];
                $banner['ContactInfo']['IMClient']=$banner['ContactInfo']['IMLogin']?$banner['ContactInfo']['IMClient']:null;
                $pointOnMap=$banner['ContactInfo']['PointOnMap']?json_decode($banner['ContactInfo']['PointOnMap'], true):null;
                if($pointOnMap){
                  ksort($pointOnMap);
                  foreach($pointOnMap as &$coord){
                    $coord=round($coord, 6);
                  }
                  unset($coord);
                }
                $banner['ContactInfo']['PointOnMap']=$pointOnMap;

              }

              //Фразы
              foreach($AdGroupForm['Phrases']->getData() as $phrase){
                $new_phrase=$phrase;
                //тут должны быть цены вся хуяня
                $new_phrase['Price']=1;
                $new_phrase['ContextPrice']=1;

                //Добавляем на яндекс
                $banner['Phrases'][]=$new_phrase;

                //Добавляем в БД
                if($key===0){
                  $NewPhrase=new Phrase();
                  $NewPhrase->fill($new_phrase)
                            ->setContextPrice(0)
                            ->setClicks(0)
                            ->setShows(0)
                            ->setMin(0)
                            ->setMax(0)
                            ->setPremiumMin(0)
                            ->setPremiumMax(0)
                            ->setMinPrice(0)
                            ->setStatusPaused('No')
                            ->setUser($NewAdGroup->getUser())
                            ->setYandexLogin($NewAdGroup->getYandexLogin())
                            ->setCampain($Campain)
                            ->setAdGroup($NewAdGroup)
                            ->setStrategy($NewAdGroup->getStrategy())
                            ->setMaxPrice($NewAdGroup->getMaxPrice());

                   $NewAdGroup->addPhrase($NewPhrase);
                }
              }

              //Быстрые ссылки
              foreach($BannerForm['Sitelinks'] as $Sitelink){
                if($Sitelink['Title']&&$Sitelink['Href']){
                  $banner['Sitelinks'][]=$Sitelink;
                }
              }

              if($AdGroupForm['MinusKeywords']->getData()){
                $banner['MinusKeywords']=explode(' ', str_replace('-', '', $AdGroupForm['MinusKeywords']->getData()));
              }

              if($key==0){
                $first_banner=$banner;

                $NewBanner->fill($banner);
                $NewAdGroup->fill($banner);
              }

              $NewAdGroup->addBanner($NewBanner);

              $banners[]=$banner;
            }

            $EM->persist($NewAdGroup);



            //Отправляем запрос на добавление 1 банера
            $add_result=$YandexApi->sendRequest('CreateOrUpdateBanners', [$first_banner], 'data');
            if(!$YandexApi->hasApiError()){
              if(is_array($add_result)&&count($add_result)){

                //Получаем данные первого банера
                $newFirstBanner=$YandexApi->sendRequest('GetBanners', ['BannerIDS'=>$add_result, 'GetPhrases'=>'No'], 'single');
                if(!$YandexApi->hasApiError()){

                    //сохраняем его id
                    foreach($banners as $key=>$banner){
                      if($key==0){
                        $banners[$key]['BannerID']=$newFirstBanner['BannerID'];
                      }

                      //и ид группы для всех
                      $banners[$key]['AdGroupID']=$newFirstBanner['AdGroupID'];
                    }


                    //Отправляем запрос на добавление уже всех банеров
                    //с учетом ид группы
                    $add_result=$YandexApi->sendRequest('CreateOrUpdateBanners', $banners, 'data');
                    if(!$YandexApi->hasApiError()){
                      if(is_array($add_result)&&count($add_result)){

                      //Получаем все объявления
                      $newBanners=$YandexApi->sendRequest('GetBanners', ['BannerIDS'=>$add_result, 'GetPhrases'=>'WithPrices'], 'data');


                      if(!$YandexApi->hasApiError()&&count($newBanners)){
                        foreach($newBanners as $key=>$newBanner){
                          $BannerForm=$AdGroupForm['Banners']->getData()[$key];

                          //Пересохраняем данные в том числе и айдишники
                          if($key==0){
                            $NewAdGroup->fill($newBanner);

                            foreach($NewAdGroup->getPhrases() as $k=>$NewPhrase){
                              $NewPhrase->fill($newBanner['Phrases'][$k]);
                            }
                          }

                          $NewAdGroup->getBanners()[$key]->fill($newBanner);

                          //Привязываем картинки
                          //Если изображение загруженное
                          //и не соответствует ранее стоявшему - меняем
                          if($BannerForm['AdImageHash']){

                            $YandexApi->sendRequest('AdImageAssociation', ['Action'=>'Set', 'AdImageAssociations'=>[ ['AdID'=>$newBanner['BannerID'], 'AdImageHash'=>$BannerForm['AdImageHash']] ] ], 'data');
                            if($YandexApi->hasApiError()){
                              $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                            }


                          //Если изображение новое - загружаем
                          } else if (!$BannerForm['AdImageHash']&&$BannerForm['AdImageUrl']){
                            $image_parts=explode('/', $BannerForm['AdImageUrl']);

                            $result=$YandexApi->sendRequest('AdImage', ['Action'=>'UploadRawData',
                                                                        'AdImageRawData'=>[ ['Login'=>$Campain->getYandexLogin()->getLogin(),
                                                                                             'RawData'=>base64_encode(file_get_contents($BannerForm['AdImageUrl'])),
                                                                                             'Name'=>isset($image_parts[count($image_parts)-1])?$image_parts[count($image_parts)-1]:'no_name',
                                                                                            ] ] ], 'data');
                            //Если нет ошибок
                            if(!$YandexApi->hasApiError()){

                              if(isset($result['ActionsResult'][0]['AdImageHash'])){
                                $YandexApi->sendRequest('AdImageAssociation', ['Action'=>'Set',
                                                                               'AdImageAssociations'=>[ ['AdID'=>$newBanner['BannerID'],
                                                                                                         'AdImageHash'=>$result['ActionsResult'][0]['AdImageHash']
                                                                                                        ] ] ], 'data');
                                if($YandexApi->hasApiError()){
                                  $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                                }

                              } else if (count($result['ActionsResult'][0]['Errors'])){
                                $this->AjaxResponse->addError($result['ActionsResult'][0]['Errors'][0]['FaultCode'], $result['ActionsResult'][0]['Errors'][0]['FaultDetail']);

                              } else {
                                $this->AjaxResponse->addError('b_023', 'Ошибка загрузки изображения на сервер Яндекса');
                              }

                            } else {
                              $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                            }
                          }

                        }

                        $EM->flush();

                        $this->AjaxResponse->setData(['url'=>$this->generateUrl('manage_ad_group_prices_edit', ['ad_group_id'=>$NewAdGroup->getId()] )], 'html', 'ok');

                      } else {
                        $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                      }



                    }
                  } else {
                    $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                  }

                } else {
                  $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                }
              }

            } else {
              $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
            }



          } else {
            $this->setAjaxFromErrors('c_045', $AdGroupForm->getErrors(true), $this->AjaxResponse);
          }

          return $this->AjaxResponse->getResponse();
        }

      } else {
        if($Request->isXmlHttpRequest()){
      	  $this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
          return $this->AjaxResponse->getResponse();
        }
    	}
    }



    return $this->render('BroAppBundle:Manage/AdGroup:add.html.twig', ['YandexLogins'=>$YandexLogins,
                                                                        'yandex_login'=> $yandex_login,
                                                                        'yandex_client'=>$yandex_client,
                                                                        'Campain'=>$Campain,
                                                                        'campain_id'=> $Campain->getId(),
                                                                        'action'=>$this->generateUrl('manage_ad_group_add', ['campain_id'=>$Campain->getId()]),
                                                                        'active_regions_names'=>$active_regions_names,
                                                                        'regionsTree'=>$this->get('bro.tree')->buildRegionsTree($regions, $active_regions),
                                                                        'workTime'=>$workTime,
                                                                        'AdGroupForm'=>$AdGroupForm->createView(),
                                                                       ]);
  }

  public function editAction($ad_group_id ){
    $Request=$this->getRequest();
		$EM=$this->getDoctrine()->getManager();
    $User = $this->getUser();
    $YandexApi = $this->get('yandex_api');

    $YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findUserRootYandexLoginsWithCampains($User->getId());
    $AdGroup=$EM->getRepository('BroAppBundle:AdGroup')->findFullById($ad_group_id, 'all', true, false, true, true);
    $YandexApi->setToken($AdGroup->getYandexLogin()->getToken());

    $bannersIDs=[];
    $yandex_login=$AdGroup->getYandexLogin()->getParentLogin()?$AdGroup->getYandexLogin()->getParentLogin()->getLogin():$AdGroup->getYandexLogin()->getLogin();
    $yandex_client=$AdGroup->getYandexLogin()->getParentLogin()?$AdGroup->getYandexLogin()->getLogin():false;

    $regions=$YandexApi->sendRequest('GetRegions', [], 'data');

    $active_regions=false;

    if(count($AdGroup->getBanners())){
      foreach($AdGroup->getBanners() as $Banner){
        $bannersIDs[]=$Banner->getBannerId();
      }

      //Получаем объявления
      $banners=$YandexApi->sendRequest('GetBanners', ['BannerIDS'=>$bannersIDs], 'data');
      if($banners&&count($banners)){

        $active_regions=explode(',', $banners[0]['Geo']);

        //Получаем изображения
        $pics=$YandexApi->sendRequest('AdImage', ['Action'=>'Get', 'SelectionCriteria'=>['Logins'=>[$AdGroup->getYandexLogin()->getLogin()], 'Assigned'=>['Yes'] ] ], 'data');

        //Привязываем изображения
        foreach($banners as &$banner){
          $banner['pic']=false;
          if($banner['AdImageHash']&&$pics['AdImages']&&count($pics['AdImages'])){
            foreach($pics['AdImages'] as $pic){
              if($pic['AdImageHash']===$banner['AdImageHash']){
                $banner['pic']=$pic;
                break;
              }
            }
          }

          //Разбираем рабочее врмя
          $banner['workTime']=[['days'=>[0, 1, 2, 3, 4], 'time'=>['from'=>['hours'=>'10', 'minutes'=>'00'],'to'=>['hours'=>'18', 'minutes'=>'00']]]];
          if(isset($banner['ContactInfo'])&&$banner['ContactInfo']['WorkTime']){
            $banner['workTime']=[];
            $workTimePeriods=array_chunk(explode(';', $banner['ContactInfo']['WorkTime']), 6);

            foreach($workTimePeriods as $workTimePeriod){
              $time_key=$workTimePeriod[2].$workTimePeriod[3].$workTimePeriod[4].$workTimePeriod[5];

              for($i=$workTimePeriod[0]; $i<=$workTimePeriod[1]; ++$i){
                $banner['workTime'][$time_key]['days'][]=$i;
              }

               $banner['workTime'][$time_key]['time']=['from'=>['hours'=>$workTimePeriod[2], 'minutes'=>$workTimePeriod[3]],
                                                       'to'=>['hours'=>$workTimePeriod[4], 'minutes'=>$workTimePeriod[5]]];
            }
          }
        }
        unset($banner);

        //Ищем имена активных регионов
        //не особо эффективно,
        //в построении дерева обход по этому массиву идет опять
        $active_regions_names=['active'=>[], 'excepted'=>[]];
        if(is_array($regions)&&is_array($active_regions)){
          foreach($regions as $region){
            if($region['RegionID']&&array_search($region['RegionID'], $active_regions)!==false){
              $active_regions_names['active'][$region['RegionID']]=$region['RegionName'];
            }
            if($region['RegionID']&&array_search('-'.$region['RegionID'], $active_regions)!==false){
              $active_regions_names['excepted'][$region['ParentID']][$region['RegionID']]=$region['RegionName'];
            }
          }
        }

//dump($AdGroup);

        $AdGroupForm=$this->createForm(new AdGroupType($banners, $banners[0]['Phrases']), null, ['validation_groups' => array('edit_ad_group')]);

        if($Request->getMethod() == 'POST') {

          $AdGroupForm->handleRequest($Request);

          if($Request->isXmlHttpRequest()){
            if($AdGroupForm->isValid()){

//dump($AdGroupForm);

              foreach($banners as $key=>$banner){
                $banners[$key]['AdGroupName']=$AdGroupForm['AdGroupName']->getData();
              }


              //Идем по списку объявлений в форме
              foreach($AdGroupForm['Banners']->getData() as $BannerForm){

                $active_banner=['key'=>false, 'data'=>[]];

                //Если объявление существующее
                if($BannerForm['BannerID']){
                  //находим его для правки
                  foreach($banners as $key=>$banner){
                    if($banner['BannerID']==$BannerForm['BannerID']){
                      $active_banner=['key'=>$key, 'data'=>$banner];

                      break;
                    }
                  }

                //Если новое
                } else {
                 $active_banner=['key'=>false, 'data'=>$banners[0]];
                 $active_banner['data']['BannerID']=null;
                }




                $active_banner['data']['Title']=$BannerForm['Title'];
                $active_banner['data']['Text']=$BannerForm['Text'];
                $active_banner['data']['Href']=$BannerForm['Href'];
                if(!$BannerForm['BannerID']){
                  $active_banner['data']['Type']=$BannerForm['Type']?'Mobile':'Desktop';
                }





                //Быстрые ссылки
                $active_banner['data']['Sitelinks']=[];
                foreach($BannerForm['Sitelinks'] as $Sitelink){
                  if($Sitelink['Title']&&$Sitelink['Href']){
                    $active_banner['data']['Sitelinks'][]=$Sitelink;
                  }
                }


                //Контакты
                if($BannerForm['ContactInfo']['Status']){

                  $active_banner['data']['ContactInfo']=$BannerForm['ContactInfo'];
                  unset($active_banner['data']['ContactInfo']['Status']);

                  $active_banner['data']['ContactInfo']['ContactPerson']=$active_banner['data']['ContactInfo']['ContactPerson']?$active_banner['data']['ContactInfo']['ContactPerson']:null;
                  $active_banner['data']['ContactInfo']['Street']=$active_banner['data']['ContactInfo']['Street']?$active_banner['data']['ContactInfo']['Street']:null;
                  $active_banner['data']['ContactInfo']['House']=$active_banner['data']['ContactInfo']['House']?$active_banner['data']['ContactInfo']['House']:null;
                  $active_banner['data']['ContactInfo']['Build']=$active_banner['data']['ContactInfo']['Build']?$active_banner['data']['ContactInfo']['Build']:null;
                  $active_banner['data']['ContactInfo']['Apart']=$active_banner['data']['ContactInfo']['Apart']?$active_banner['data']['ContactInfo']['Apart']:null;
                  $active_banner['data']['ContactInfo']['PhoneExt']=$active_banner['data']['ContactInfo']['PhoneExt']?$active_banner['data']['ContactInfo']['PhoneExt']:'';
                  $active_banner['data']['ContactInfo']['IMLogin']=$active_banner['data']['ContactInfo']['IMLogin']?$active_banner['data']['ContactInfo']['IMLogin']:'';
                  $active_banner['data']['ContactInfo']['ExtraMessage']=$active_banner['data']['ContactInfo']['ExtraMessage']?$active_banner['data']['ContactInfo']['ExtraMessage']:null;
                  $active_banner['data']['ContactInfo']['ContactEmail']=$active_banner['data']['ContactInfo']['ContactEmail']?$active_banner['data']['ContactInfo']['ContactEmail']:null;
                  $active_banner['data']['ContactInfo']['OGRN']=$active_banner['data']['ContactInfo']['OGRN']?$active_banner['data']['ContactInfo']['OGRN']:null;

                  $active_banner['data']['ContactInfo']['CountryCode']='+'.$active_banner['data']['ContactInfo']['CountryCode'];
                  $active_banner['data']['ContactInfo']['IMClient']=$active_banner['data']['ContactInfo']['IMLogin']?$active_banner['data']['ContactInfo']['IMClient']:null;

                  $pointOnMap=$active_banner['data']['ContactInfo']['PointOnMap']?json_decode($active_banner['data']['ContactInfo']['PointOnMap'], true):null;
                  if($pointOnMap){
                    ksort($pointOnMap);
                    foreach($pointOnMap as &$coord){
                      $coord=round($coord, 6);
                    }
                    unset($coord);
                  }
                  $active_banner['data']['ContactInfo']['PointOnMap']=$pointOnMap;

                } else if(isset($active_banner['data']['ContactInfo'])) {
                  unset($active_banner['data']['ContactInfo']);
                }




                //Проходим по фразам из формы
                $actualPhrasesIDs=[];
                foreach($AdGroupForm['Phrases']->getData() as $phrase){
                  if($phrase['PhraseID']){

                    $actualPhrasesIDs[$phrase['PhraseID']]=true;

                    //Если она есть у яндекса меняем
                    foreach($active_banner['data']['Phrases'] as $key=>$yandex_phrase){
                      if($yandex_phrase['PhraseID']==$phrase['PhraseID']){
                        $active_banner['data']['Phrases'][$key]=array_replace($yandex_phrase, $phrase);
                        break;
                      }
                    }

                    //если она есть в БД - меняем
                    if(isset($AdGroup->GetPhrases()[$phrase['PhraseID']])){
                      $AdGroup->GetPhrases()[$phrase['PhraseID']]->fill($phrase);
                    }

                  //Если у фразы нет id но есть текст то она новая
                  } else if($phrase['Phrase']){

                    $new_phrase=$phrase;
                    //тут должны быть цены вся хуяня
                    $new_phrase['Price']=1;

                    //Добавляем на яндекс
                    $active_banner['data']['Phrases'][]=$new_phrase;

                    //Добавляем в БД
                    $NewPhrase=new Phrase();
                    $NewPhrase->fill($new_phrase)
                              ->setContextPrice(0)
                              ->setClicks(0)
                              ->setShows(0)
                              ->setMin(0)
                              ->setMax(0)
                              ->setPremiumMin(0)
                              ->setPremiumMax(0)
                              ->setMinPrice(0)
                              ->setStatusPaused('No')
                              ->setUser($AdGroup->getUser())
                              ->setYandexLogin($AdGroup->getYandexLogin())
                              ->setCampain($AdGroup->getCampain())
                              ->setAdGroup($AdGroup)
                              ->setStrategy($AdGroup->getStrategy())
                              ->setMaxPrice($AdGroup->getMaxPrice());

                    //$AdGroup->addPhrase($NewPhrase);
                  }
                }

                //проходим по фразам в БД для удаления
                foreach($AdGroup->GetPhrases() as $Phrase){
                  if(!isset($actualPhrasesIDs[$Phrase->getPhraseID()])){
                    $EM->remove($Phrase);
                  }
                }

                //Если нет яндендесовой фразы в форме - удаляем
                foreach($active_banner['data']['Phrases'] as $key=>$yandex_phrase){
                  if($yandex_phrase['PhraseID']&&!isset($actualPhrasesIDs[$yandex_phrase['PhraseID']])){
                    unset($active_banner['data']['Phrases'][$key]);
                  }
                }
                $active_banner['data']['Phrases']=array_values($active_banner['data']['Phrases']);




                $active_banner['data']['MinusKeywords']=explode(' ', str_replace('-', '', $AdGroupForm['MinusKeywords']->getData()));
                $active_banner['data']['Geo']=implode(',', json_decode($AdGroupForm['Geo']->getData(), true));
                $active_banner['data']['AdGroupMobileBidAdjustment']=$AdGroupForm['AdGroupMobileBidAdjustment']->getData();


                //Сохраняем новые данные для яндекса
                if($active_banner['key']!==false){
                  $banners[$active_banner['key']]=$active_banner['data'];
                  $AdGroup->getBanners()[$active_banner['data']['BannerID']]->fill($active_banner['data'])
                                                                            ->setStatusBannerModerate('Pending');
                } else {
                  $banners[]=$active_banner['data'];
                  $Banner=new Banner();
        					$Banner->fill($active_banner['data'])
                         ->setStatusBannerModerate('Pending')
                         ->SetUser($AdGroup->getUser())
                         ->SetYandexLogin($AdGroup->getYandexLogin())
      									 ->SetCampain($AdGroup->getCampain())
                         ->SetAdGroup($AdGroup);
                  $AdGroup->addBanner($Banner);
                }


              }

              //Отправляем запрос
              $edit_result=$YandexApi->sendRequest('CreateOrUpdateBanners', $banners, 'data');

              if(!$YandexApi->hasApiError()){

                //Привязываем картинки
                if(is_array($edit_result)&&count($edit_result)){
                  foreach($edit_result as $key=>$bannerID){
                    $BannerForm=$AdGroupForm['Banners']->getData()[$key];
                    $active_banner=['key'=>false, 'data'=>[]];

                    //Если объявление ранее существующее
                    //находим его для правки
                    foreach($banners as $key=>$banner){
                      if($banner['BannerID']==$bannerID){
                        $active_banner=['key'=>$key, 'data'=>$banner];

                        break;
                      }
                    }


                    //Если изображение загруженное
                    //и не соответствует ранее стоявшему - меняем
                    if($BannerForm['AdImageHash']&&(!isset($active_banner['data']['AdImageHash'])||(isset($active_banner['data']['AdImageHash'])&&$active_banner['data']['AdImageHash']!==$BannerForm['AdImageHash']))){

                      $YandexApi->sendRequest('AdImageAssociation', ['Action'=>'Set', 'AdImageAssociations'=>[ ['AdID'=>$bannerID, 'AdImageHash'=>$BannerForm['AdImageHash']] ] ], 'data');
                      if($YandexApi->hasApiError()){
                        $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                      }


                    //Если изображение новое - загружаем
                    } else if (!$BannerForm['AdImageHash']&&$BannerForm['AdImageUrl']){
                      $image_parts=explode('/', $BannerForm['AdImageUrl']);

                      $result=$YandexApi->sendRequest('AdImage', ['Action'=>'UploadRawData',
                                                                  'AdImageRawData'=>[ ['Login'=>$AdGroup->getYandexLogin()->getLogin(),
                                                                                       'RawData'=>base64_encode(file_get_contents($BannerForm['AdImageUrl'])),
                                                                                       'Name'=>isset($image_parts[count($image_parts)-1])?$image_parts[count($image_parts)-1]:'no_name',
                                                                                      ] ] ], 'data');
                      //Если нет ошибок
                      if(!$YandexApi->hasApiError()){

                        if(isset($result['ActionsResult'][0]['AdImageHash'])){
                          $YandexApi->sendRequest('AdImageAssociation', ['Action'=>'Set',
                                                                         'AdImageAssociations'=>[ ['AdID'=>$bannerID,
                                                                                                   'AdImageHash'=>$result['ActionsResult'][0]['AdImageHash']
                                                                                                  ] ] ], 'data');
                          if($YandexApi->hasApiError()){
                            $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                          }

                        } else if (count($result['ActionsResult'][0]['Errors'])){
                          $this->AjaxResponse->addError($result['ActionsResult'][0]['Errors'][0]['FaultCode'], $result['ActionsResult'][0]['Errors'][0]['FaultDetail']);

                        } else {
                          $this->AjaxResponse->addError('b_023', 'Ошибка загрузки изображения на сервер Яндекса');
                        }

                      } else {
                        $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                      }
                    }
                  }

                  //Сохраняем новые фразы
                  $actual_banners_with_phrases=$YandexApi->sendRequest('GetBanners', ['BannerIDS'=>$edit_result,
                                                                                      'FieldsNames'=>['BannerID'],
                                                                                      'GetPhrases'=>'WithPrices'
                                                                                     ], 'data');
                  if(!$YandexApi->hasApiError()){

                    foreach($actual_banners_with_phrases[0]['Phrases'] as $phrase){
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
                    }
                  }
                }



                //Сохраняем у нас
                $AdGroup->fill($banners[0]);

                $i=0;
                foreach($AdGroup->getBanners() as $Banner){
                  if(!$Banner->getBannerId()){
                    $Banner->setBannerId($edit_result[$i]);
                  }
                  ++$i;
                }

                $EM->flush();

                $this->AjaxResponse->setData(['BannerIDS'=>$edit_result], 'json', 'ok');
                //$this->AjaxResponse->setData(array('content'=>$this->forwardBack(true, true)->getContent()), 'html', 'ok');

              } else {
                $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
              }

            } else {
              $this->setAjaxFromErrors('c_012', $AdGroupForm->getErrors(true), $this->AjaxResponse);
            }

            return $this->AjaxResponse->getResponse();
          }
        }

        return $this->render('BroAppBundle:Manage/AdGroup:edit.html.twig', ['YandexLogins'=>$YandexLogins,
                                                                            'yandex_login'=> $yandex_login,
                                                                            'yandex_client'=>$yandex_client,
                                                                            'campain_id'=> $AdGroup->getCampain()->getId(),
                                                                            'action'=>$this->generateUrl('manage_ad_group_edit', ['ad_group_id'=>$ad_group_id]),
                                                                            'AdGroup'=>$AdGroup,
                                                                            'Campain'=>$AdGroup->getCampain(),
                                                                            'active_regions_names'=>$active_regions_names,
                                                                            'regionsTree'=>$this->get('bro.tree')->buildRegionsTree($regions, $active_regions),
                                                                            'AdGroupForm'=>$AdGroupForm->createView(),
                                                                            ]);
      }
    }




    return $this->render('BroAppBundle:Manage/AdGroup:edit.html.twig', ['YandexLogins'=>$YandexLogins,
                                                                        'yandex_login'=> $yandex_login,
                                                                        'yandex_client'=>$yandex_client,
                                                                        'campain_id'=> $AdGroup->getCampain()->getId()
                                                                        ]);
  }




  public function editPricesAction($ad_group_id){
    $Request=$this->getRequest();
    $EM=$this->getDoctrine()->getManager();
    $User = $this->getUser();
    $YandexApi = $this->get('yandex_api5');

    $YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findUserRootYandexLoginsWithCampains($User->getId());
    $AdGroup=$EM->getRepository('BroAppBundle:AdGroup')->findFullById($ad_group_id, 'all', false,  true, true,  true);
    $Phrases=$EM->getRepository('BroAppBundle:Phrase')->findByAdGroup($ad_group_id);
    $Strategys=$EM->getRepository('BroAppBundle:Strategy')->findUserStrategys($User->getId());
    $YandexApi->setToken5($AdGroup->getYandexLogin()->getToken(), $AdGroup->getYandexLogin());

    $yandex_login=$AdGroup->getYandexLogin()->getParentLogin()?$AdGroup->getYandexLogin()->getParentLogin()->getLogin():$AdGroup->getYandexLogin()->getLogin();
    $yandex_client=$AdGroup->getYandexLogin()->getParentLogin()?$AdGroup->getYandexLogin()->getLogin():false;

    $bannersIDs=[];
    if(count($AdGroup->getBanners())){

      foreach($AdGroup->getBanners() as $Banner){
        $bannersIDs[]=$Banner->getBannerId();
      }

      //Получаем объявления
      //$banners=$YandexApi->sendRequest('GetBanners', ['BannerIDS'=>$bannersIDs, 'GetPhrases'=>'WithPrices'], 'data');
        $request_data=['SelectionCriteria'=>['AdGroupIds'=>[$AdGroup->getAdGroupID()]]];
        $request_data=array_merge($request_data, $EM->getRepository('BroAppBundle:Banner')->getStdYandexApiParams());

        $banners=$YandexApi->send5Request('ads', 'get', $request_data, [], 'Ads');

        $request_data=['SelectionCriteria'=>['AdGroupIds'=>[$AdGroup->getAdGroupID()]]];
        $request_data=array_merge($request_data, $EM->getRepository('BroAppBundle:Phrase')->getStdYandexApiParams());
        $phrases=$YandexApi->send5Request('keywords', 'get', $request_data, [], 'Keywords');


        $request_data=['SelectionCriteria'=>['AdGroupIds'=>[$AdGroup->getAdGroupID()]], 'FieldNames'=>['KeywordId', 'CompetitorsBids', 'SearchPrices'] ];
        $bids=$YandexApi->send5Request('bids', 'get', $request_data, [], 'Bids');

        foreach($phrases as &$phrase){
            foreach($bids as $bid){
                if($bid['KeywordId']==$phrase['Id']){
                    foreach($bid['SearchPrices'] as $searchPrice){
                        if($searchPrice['Position']=='PREMIUMFIRST'){
                            $phrase['PremiumMax']=$searchPrice['Price']/1000000;
                        } else if($searchPrice['Position']=='PREMIUMBLOCK'){
                            $phrase['PremiumMin']=$searchPrice['Price']/1000000;
                        } else if($searchPrice['Position']=='FOOTERFIRST'){
                            $phrase['Max']=$searchPrice['Price']/1000000;
                        } else if($searchPrice['Position']=='FOOTERBLOCK'){
                            $phrase['Min']=$searchPrice['Price']/1000000;
                        }
                    }


                    break;
                }
            }
        }
        unset($phrase);

        $banners[0]['Phrases']=$phrases;
        //dump($banners);

      if(!$YandexApi->hasApiError()&&$banners&&count($banners)){

        if($Request->getMethod() == 'GET') {
          //Подставляем цены в зависимости от стратегии
          if(isset($banners[0]['Phrases'])&&count($banners[0]['Phrases'])){
            foreach($banners[0]['Phrases'] as $key=>$phrase){
              //$phrase['Price']=9;

              if(isset($AdGroup->getPhrases()[$phrase['Id']])){
                $PhraseStrategy=$AdGroup->getPhrases()[$phrase['Id']]->getStrategy();
                $phraseMaxPrice=$AdGroup->getPhrases()[$phrase['Id']]->getMaxPrice();
              } else {
                $PhraseStrategy=$AdGroup->getStrategy();
                $phraseMaxPrice=$AdGroup->getMaxPrice();
              }

              $bid=$this->get('price_api')->parceStrategy($PhraseStrategy,
                                                          $phrase['Bid']/1000000,
                                                          $phraseMaxPrice,
                                                          $phrase['Min'],
                                                          $phrase['Max'],
                                                          $phrase['PremiumMin'],
                                                          $phrase['PremiumMax']);
/*dump($PhraseStrategy->getView());
dump($phrase['Price']);
dump($phraseMaxPrice);
dump($phrase['Min']);
dump($phrase['Max']);
dump($phrase['PremiumMin']);
dump($phrase['PremiumMax']);
dump($bid);*/
$banners[0]['Phrases'][$key]['Price']=($bid['real_price']!==false)?round($bid['real_price'], 2):0;
              $phrase['Price']=($bid['real_price']!==false)?round($bid['real_price'], 2):0;

            }
            unset($phrase);
          }
        }

        $AdGroupPricesForm=$this->createForm(new AdGroupPricesType($banners[0]['Phrases']), null, ['validation_groups' => array('edit_ad_group_prices')]);

        if($Request->getMethod() == 'POST') {

          $AdGroupPricesForm->handleRequest($Request);

          if($AdGroupPricesForm->isValid()){
            if($Request->isXmlHttpRequest()){
              $phrases_data=[];
                //dump($AdGroupPricesForm['Phrases']->getData());
              foreach($AdGroupPricesForm['Phrases']->getData() as $phrase){

                if(isset($Phrases[$phrase['Id']])){
                  $Phrases[$phrase['Id']]->setPrice($phrase['Price']);
                }

                $phrases_data[]=['KeywordId'=>$phrase['Id'],
                                 'Bid'=>$phrase['Price']*1000000
                                ];
              }


//dump($phrases_data);
              $YandexApi->send5Request('bids', 'set', ['Bids'=>$phrases_data], []);
              if(!$YandexApi->hasApiError()){
                $EM->flush();
                $this->AjaxResponse->setStatus('ok');
              } else {
                $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
              }

              return $this->AjaxResponse->getResponse();

            } else {

              $this->get('session')->getFlashBag()->add('success','Новые ставки успешно сохранены');

              if($yandex_client==false){
                return $this->redirectToRoute('manage_campain', ['yandex_login'=>$yandex_login, 'campain_id'=> $AdGroup->getCampain()->getId()]);
              } else {
                return $this->redirectToRoute('manage_client_campain', ['yandex_login'=>$yandex_login,  'yandex_client'=> $yandex_client,  'campain_id'=> $AdGroup->getCampain()->getId()]);
              }
            }

          } else {
            if($Request->isXmlHttpRequest()){
              $this->setAjaxFromErrors('c_012', $AdGroupPricesForm->getErrors(true), $this->AjaxResponse);
              return $this->AjaxResponse->getResponse();
            } else {
              $this->get('session')->getFlashBag()->add('error', 'Введены некоректные ставки');
            }
          }

        }



        return $this->render('BroAppBundle:Manage/AdGroup:edit_prices.html.twig', ['standalone'=>$Request->attributes->get('standalone'),
                                                                                   'only_content'=>$Request->attributes->get('only_content'),
                                                                                   'YandexLogins'=>$YandexLogins,
                                                                                   'yandex_login'=> $yandex_login,
                                                                                   'yandex_client'=>$yandex_client,
                                                                                   'campain_id'=> $AdGroup->getCampain()->getId(),
                                                                                   'banners'=>$banners,
                                                                                   'AdGroup'=>$AdGroup,
                                                                                   'Strategys'=>$Strategys,
                                                                                   'AdGroupPricesForm'=>$AdGroupPricesForm->createView()
                                                                                  ]);
      }
    }

    return $this->render('BroAppBundle:Manage/AdGroup:edit_prices.html.twig', ['YandexLogins'=>$YandexLogins,
                                                                              'yandex_login'=> $yandex_login,
                                                                              'yandex_client'=>$yandex_client,
                                                                              'campain_id'=> $AdGroup->getCampain()->getId()
                                                                             ]);
}


  public function editAdGroupStrategyAction($ad_group_id, $strategy_id=false, $render_response=true){
    $Request=$this->getRequest();
		$EM=$this->getDoctrine()->getManager();
		$AdGroups=$EM->getRepository('BroAppBundle:AdGroup')->findFullById($ad_group_id, true, false, false, true);

    if(!$strategy_id){
      $strategy_id=$Request->request->get('strategy_id');
    }

    $Strategy=$EM->getRepository('BroAppBundle:Strategy')->findOneById($strategy_id);

		if ($Request->getMethod() == 'POST') {

			if($Strategy){

        foreach($AdGroups as &$AdGroup){

  				if($EM->getRepository('BroAppBundle:AdGroup')->checkUserAcces($AdGroup->getId(), $this->getUser())){

            $AdGroup->setStrategy($Strategy);

            if(count($AdGroup->GetPhrases())>0){
              $Phrases=$AdGroup->GetPhrases();

              foreach($Phrases as &$Phrase){
                $Phrase->setStrategy($Strategy);
              }
              unset($Phrase);
            }

  				} else {
  					$this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
            break;
  				}
        }
        unset($AdGroup);

        //Если нет ошибок отправляем в базу и отдаем ответ
        if(!$this->AjaxResponse->getHasErrors()){
          $EM->flush();
          //Если нужно вернуть рендер
          if($render_response){
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

  public function editAdGroupMaxPriceAction($ad_group_id, $max_price=false, $render_response=true){
    $Request=$this->getRequest();
		$EM=$this->getDoctrine()->getManager();

    if($max_price===false){
      $max_price=$Request->request->get('max_price');
    }
		$AdGroups=$EM->getRepository('BroAppBundle:AdGroup')->findFullById($ad_group_id, true, false, false, true);

		if ($Request->getMethod() == 'POST') {
      foreach($AdGroups as &$AdGroup){
    		if($EM->getRepository('BroAppBundle:AdGroup')->checkUserAcces($AdGroup->getId(), $this->getUser())){

          $AdGroup->setMaxPrice($max_price);
          if(count($AdGroup->GetPhrases())>0){
            $Phrases=$AdGroup->GetPhrases();
            foreach($Phrases as &$Phrase){
              $Phrase->setMaxPrice($max_price);
            }
            unset($Phrase);
          }
    		} else {
    			$this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
          break;
    		}
      }
      unset($AdGroup);

      //Если нет ошибок отправляем в базу и отдаем ответ
      if(!$this->AjaxResponse->getHasErrors()){
        $EM->flush();

        //Если нужно вернуть рендер
        if($render_response){
         /* $referer_params = $this->get('router')->match(str_replace('http://'.$_SERVER['HTTP_HOST'], '', $_SERVER['HTTP_REFERER']));
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

			return $this->AjaxResponse->getResponse();
		}

	 return new Response();

  }


  //**************************************************
  //****************** API ДЕЙСТВИЯ ******************
  //**************************************************
  public function controllAdGroupAction($ad_group_id, $action, $render_response=true){
    $YandexApi = $this->get('yandex_api5');
    $EM=$this->getDoctrine()->getManager();

    $AdGroups=$EM->getRepository('BroAppBundle:AdGroup')->findFullById($ad_group_id, true, false, true);

    //НЕ УДАЛЯТЬ
    //Мотод то полностью рабочий но не надежный,
    //потому что если действий несколько в случае ошибки на яндексе на втором этапе будет рассинхрон с базой
    //НО он гараздо быстрее, в несколько раз
    //возможно лучше использовать именно его, или переделать основываясь на этой схеме
/*    if(count($Banners)>0){
      $request_data=array();
      $bad_banners=array();
      $pre_action=false;

      foreach($Banners as $key=>&$Banner){
        if($EM->getRepository('BroAppBundle:Banner')->checkUserAcces($Banner->getId(), $this->getUser())){
          if($key==0){
            $YandexApi->setToken($Banner->getCampain()->getYandexLogin()->getToken());
            $request_data['CampaignID']=$Banner->getCampain()->getCampaignID();
          }
          $request_data['BannerIDS'][]=$Banner->getBannerId();


          if($action=='StopBanners'){
            $Banner->setStatusShow('No');

          } else if($action=='ResumeBanners'){
            $Banner->setStatusShow('Yes');

            if($Banner->getStatusArchive()=='Yes'){
              $pre_action='UnArchiveBanners';
              $bad_banners[]=$Banner->getBannerId();
              $Banner->setStatusArchive('No');
            }

          } else if($action=='ArchiveBanners'){
            $Banner->setStatusArchive('Yes');

            if($Banner->getStatusShow()=='Yes'){
              $pre_action='StopBanners';
              $bad_banners[]=$Banner->getBannerId();
              $Banner->setStatusShow('No');
            }

          } else if($action=='UnArchiveBanners'){
            $Banner->setStatusArchive('No');
          }

        } else {
    			$this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
          break;
    		}
      }
      unset($Banner);

      //Если все ок отсылаем апи запрос у яндексу и редактируем базу
      if(!$this->AjaxResponse->getHasErrors()){

        //Выполняем подготовительные действия для банеров
        if(count($bad_banners)>0){
          if(!$YandexApi->sendRequest($pre_action, array('CampaignID'=>$request_data['CampaignID'],'BannerIDS'=>$bad_banners))){
            $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
          }
        }

        //Если все ок отсылаем апи запрос у яндексу и редактируем базу
        if(!$this->AjaxResponse->getHasErrors()){
          if($YandexApi->sendRequest($action, $request_data)){
            $EM->flush();
            //Если нужно вернуть рендер
            if($render_response){
              $this->AjaxResponse->setData(array('workflow'=>$this->forwardBack()->getContent()), 'html', 'ok');
            } else {
              $this->AjaxResponse->setStatus('ok');
            }
          } else {
            $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
          }
        }
      }
    }*/

    //Заебись было бы конечно сделать
    //безопасную массовую загрузку изменений
    if(count($AdGroups)>0){
      $request_data=array();

      $bannersNewStatuses=[];
      $bannersStatuses=[];
      foreach($AdGroups as $key=>&$AdGroup){
        $bannersIDs=[];

        if($EM->getRepository('BroAppBundle:AdGroup')->checkUserAcces($AdGroup->getId(), $this->getUser())){

          if($key==0){
            $YandexApi->setToken5($AdGroup->getYandexLogin()->getToken(), $AdGroup->getYandexLogin());
          }

          if(count($AdGroup->getBanners())){

            foreach($AdGroup->getBanners() as $Banner) {
              $bannersIDs[]=$Banner->getBannerId();

              //Так как статусы всех банеров долхны совпадать
              //но опасно если это изменится
              $bannersStatuses['StatusShow']=$Banner->getStatusShow();
              $bannersStatuses['StatusArchive']=$Banner->getStatusArchive();
            }

            //$request_data=array('BannerIDS'=>$bannersIDs);
            $request_data=['SelectionCriteria'=>['Ids'=>$bannersIDs]];

            //Остановка банера
            if($action=='StopBanners'&&$YandexApi->send5Request('ads', 'suspend', $request_data)){
              $bannersNewStatuses['StatusShow']='No';
            }

            //Возобнавление банера
            if($action=='ResumeBanners'){
              if($bannersStatuses['StatusArchive']=='Yes'&&$YandexApi->send5Request('ads', 'unarchive', $request_data)){
                $bannersNewStatuses['StatusArchive']='No';
              }

              if(!$YandexApi->hasApiError()&&$YandexApi->send5Request('ads', 'resume', $request_data)){
                $bannersNewStatuses['StatusShow']='Yes';
              }
            }

            //Архивирование
            if($action=='ArchiveBanners'){
              if($bannersStatuses['StatusShow']=='Yes'&&$YandexApi->send5Request('ads', 'suspend', $request_data)){
                $bannersNewStatuses['StatusShow']='No';
              }

              if(!$YandexApi->hasApiError()&&$YandexApi->send5Request('ads', 'archive', $request_data)){
                $bannersNewStatuses['StatusArchive']='Yes';
              }
            }

            //Разархивирование
            if($action=='UnArchiveBanners'&&$YandexApi->send5Request('ads', 'unarchive', $request_data)){
              $bannersNewStatuses['StatusArchive']='No';
            }


            //Ставим статусы
            foreach($AdGroup->getBanners() as $Banner) {
              if(count($bannersNewStatuses)){
                foreach($bannersNewStatuses as $key=>$bannerNewStatuse){
                  $setter='Set'.$key;
				 	 		    $Banner->$setter($bannerNewStatuse);
                }
              }
            }



            if(!$YandexApi->hasApiError()){
              $EM->flush();
            } else {
              $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
            }

            if(!$this->AjaxResponse->getHasErrors()){

              if($render_response){
                $this->AjaxResponse->setData(array('time'=>$YandexApi->getRequestTime(), 'content'=>$this->forwardBack(true, true)->getContent()), 'html', 'ok');
              } else {
                $this->AjaxResponse->setStatus('ok');
              }
            }

          }

        } else {
    			$this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
          break;
    		}
      }
      unset($AdGroup);

    }

    return $this->AjaxResponse->getResponse();
  }


}
