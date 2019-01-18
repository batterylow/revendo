<?php

namespace Bro\AppBundle\Controller\Manage;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Bro\AppBundle\Entity\AjaxResponse;

use Bro\AppBundle\Entity\Strategy;
use Bro\AppBundle\Entity\Banner;

use Bro\AppBundle\Form\Type\Manage\UploadBannerImageType;

use Bro\AppBundle\Controller\Manage\ManageController;
use Bro\ApiBundle\Controller\YandexApiController;

class BannersController extends ManageController {

	private $AjaxResponse;

	function __construct(){
		$this->AjaxResponse=AjaxResponse::getInstance();
	}

  public function deleteImageAction($BannerID){
    $Request=$this->getRequest();
		$EM=$this->getDoctrine()->getManager();
    $YandexApi = $this->get('yandex_api');

    $Banner=$EM->getRepository('BroAppBundle:Banner')->findOneBy(['BannerID'=>$BannerID]);

    if ($Request->getMethod() == 'POST') {
      if($Request->isXmlHttpRequest()){

        if($Banner){
          $YandexApi->setToken($Banner->getYandexLogin()->getToken());

          if($EM->getRepository('BroAppBundle:Banner')->checkUserAcces($Banner->getId(), $this->getUser())){



            $YandexApi->sendRequest('AdImageAssociation', ['Action'=>'Set', 'AdImageAssociations'=>[ ['AdID'=>$BannerID] ] ], 'data');
            if(!$YandexApi->hasApiError()){
              $this->AjaxResponse->setStatus('ok');
            } else {
              $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
            }

          } else {
            $this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
          }
        }

        return $this->AjaxResponse->getResponse();
      }
    }

    return new Response();
  }


  public function uploadImageFromWebAction($contaner=false){
    $Request=$this->getRequest();
		$EM=$this->getDoctrine()->getManager();

    $UploadBannerImageForm=$this->createForm(new UploadBannerImageType());
    $UploadBannerImageForm->get('contaner')->setData($contaner);

    if ($Request->getMethod() == 'POST') {

      $UploadBannerImageForm->handleRequest($Request);


      if($Request->isXmlHttpRequest()){

        if($UploadBannerImageForm->isValid()){

          $mimes=['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
          $extentions=['.png', '.jpeg', '.jpg', '.gif'];

          $image=$UploadBannerImageForm['image']->getData();
          $extention='.'.substr(strrchr($image, '.'), 1);
          @$image_info = getimagesize($image);
          $dir='/upload/banners_pics/';
          $new_image=md5(microtime()).$extention;
          $new_image_path=$_SERVER['DOCUMENT_ROOT'].$dir.$new_image;

          if($image_info&&array_search($image_info['mime'], $mimes)!==false){
            $ch = curl_init($image);
            $fp = fopen($new_image_path, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_exec($ch);
            $content_type=curl_getinfo($ch, CURLINFO_CONTENT_TYPE);


            /*if(array_search($image_info['mime'], $mimes)===false){
              unlink($_SERVER['DOCUMENT_ROOT'].$dir.$new_image);
              $this->AjaxResponse->addError('b_012', 'К загрузке разрешены только картинки c разрешениями .png .jpg .jpeg .gif');
            } else {*/

              $this->AjaxResponse->setData(['image'=>['name'=>$new_image, 'url'=>'http://'.$this->container->getParameter('site').$dir.$new_image],
                                            'dir'=>$dir,
                                            'contaner'=>$UploadBannerImageForm['contaner']->getData()], 'json', 'ok');
            //}

            curl_close($ch);
            fclose($fp);

          } else {
             $this->AjaxResponse->addError('b_012', 'К загрузке разрешены только картинки c разрешениями .png .jpg .jpeg .gif');
          }

        } else {

          $this->setAjaxFromErrors('c_012', $UploadBannerImageForm->getErrors(true), $this->AjaxResponse);
        }

        return $this->AjaxResponse->getResponse();
      }
    }

    return $this->render('BroAppBundle:Manage/AdGroup:Forms/UploadBannerImageForm.html.twig', ['UploadBannerImageForm'=>$UploadBannerImageForm->createView()]);
  }


   public function uploadedBanerImagesAction($BannerID, $contaner=false){
    $Request=$this->getRequest();
		$EM=$this->getDoctrine()->getManager();
    $YandexApi = $this->get('yandex_api');

    $Banner=$EM->getRepository('BroAppBundle:Banner')->findOneBy(['BannerID'=>$BannerID]);
    $banner_pics=[];
    $pics_hashes=[];

    if($Banner){
      $YandexApi->setToken($Banner->getYandexLogin()->getToken());

      $pics=$YandexApi->sendRequest('AdImageAssociation', ['Action'=>'Get',
                                                           'SelectionCriteria'=>['Logins'=>[$Banner->getYandexLogin()->getLogin()],
                                                                                 'CampaignID'=>[$Banner->getCampain()->getCampaignID()]
                                                                                ]
                                                          ], 'data');

      if(!$YandexApi->hasApiError()&&count($pics['AdImageAssociations'])){
        foreach($pics['AdImageAssociations'] as $pic){
          $pics_hashes[]=$pic['AdImageHash'];
        }

        $pics=$YandexApi->sendRequest('AdImage', ['Action'=>'Get',
                                                 'SelectionCriteria'=>['Logins'=>[$Banner->getYandexLogin()->getLogin()],
                                                                       'AdImageHashes'=>$pics_hashes
                                                                      ]
                                                 ], 'data');

        if(!$YandexApi->hasApiError()&&count($pics['AdImages'])){
          $banner_pics=$pics['AdImages'];
        }

      }



      return $this->render('BroAppBundle:Manage/AdGroup:uploaded_banners_images.html.twig', ['banner_pics'=>$banner_pics]);
    }

    return new Response();
   }


  public function editBannerStrategyAction($banner_id, $strategy_id=false, $render_response=true){
    $Request=$this->getRequest();
		$EM=$this->getDoctrine()->getManager();
		$Banners=$EM->getRepository('BroAppBundle:Banner')->findFullById($banner_id);

    if(!$strategy_id){
      $strategy_id=$Request->request->get('strategy_id');
    }

    $Strategy=$EM->getRepository('BroAppBundle:Strategy')->findOneById($strategy_id);

		if ($Request->getMethod() == 'POST') {

			if($Strategy){

        foreach($Banners as &$Banner){

  				if($EM->getRepository('BroAppBundle:Banner')->checkUserAcces($Banner->getId(), $this->getUser())){

            $Banner->setStrategy($Strategy);

            if(count($Banner->GetPhrases())>0){
              $Phrases=$Banner->GetPhrases();
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
        unset($Banner);

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

  public function editBannerMaxPriceAction($banner_id, $max_price=false, $render_response=true){
    $Request=$this->getRequest();
		$EM=$this->getDoctrine()->getManager();
    if($max_price===false){
      $max_price=$Request->request->get('max_price');
    }
		$Banners=$EM->getRepository('BroAppBundle:Banner')->findFullById($banner_id);

		if ($Request->getMethod() == 'POST') {
      foreach($Banners as &$Banner){
    		if($EM->getRepository('BroAppBundle:Banner')->checkUserAcces($Banner->getId(), $this->getUser())){

          $Banner->setMaxPrice($max_price);
          if(count($Banner->GetPhrases())>0){
            $Phrases=$Banner->GetPhrases();
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
      unset($Banner);

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
    //Не используется
  public function controllBannerAction($banner_id, $action, $render_response=true){
    $YandexApi = $this->get('yandex_api');
    $EM=$this->getDoctrine()->getManager();

    $Banners=$EM->getRepository('BroAppBundle:Banner')->findFullById($banner_id, true, true);

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
    if(count($Banners)>0){
      $request_data=array();

      foreach($Banners as $key=>&$Banner){

        if($EM->getRepository('BroAppBundle:Banner')->checkUserAcces($Banner->getId(), $this->getUser())){
          if($key==0){
            $YandexApi->setToken($Banner->getYandexLogin()->getToken());
          }

           $request_data=array('CampaignID'=>$Banner->getCampain()->getCampaignID(),
                               'BannerIDS'=>array($Banner->getBannerId()));


          //Остановка банера
          if($action=='StopBanners'&&$result=$YandexApi->sendRequest('StopBanners', $request_data)){
             $Banner->setStatusShow('No');
          }

          //Возобнавление банера
          if($action=='ResumeBanners'){
            if($Banner->getStatusArchive()=='Yes'&&$result=$YandexApi->sendRequest('UnArchiveBanners', $request_data)){
              $Banner->setStatusArchive('No');
            }

            if(!$YandexApi->hasApiError()&&$result=$YandexApi->sendRequest('ResumeBanners', $request_data)){
              $Banner->setStatusShow('Yes');
            }
          }

          //Архивирование
          if($action=='ArchiveBanners'){
            if($Banner->getStatusShow()=='Yes'&&$result=$YandexApi->sendRequest('StopBanners', $request_data)){
              $Banner->setStatusShow('No');
            }

            if(!$YandexApi->hasApiError()&&$result=$YandexApi->sendRequest('ArchiveBanners', $request_data)){
              $Banner->setStatusArchive('Yes');
            }
          }



          //Разархивирование
          if($action=='UnArchiveBanners'&&$result=$YandexApi->sendRequest('UnArchiveBanners', $request_data)){
            $Banner->setStatusArchive('No');
          }

          $EM->flush();

          if($YandexApi->hasApiError()){
            $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
          }

          if(!$this->AjaxResponse->getHasErrors()){

            if($render_response){
              $this->AjaxResponse->setData(array('time'=>$YandexApi->getRequestTime(), 'content'=>$this->forwardBack(true, true)->getContent()), 'html', 'ok');
            } else {
              $this->AjaxResponse->setStatus('ok');
            }
          }

        } else {
    			$this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
          break;
    		}
      }
      unset($Banner);

    }

    return $this->AjaxResponse->getResponse();
  }


}
