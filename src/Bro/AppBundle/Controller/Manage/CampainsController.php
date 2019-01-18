<?php

namespace Bro\AppBundle\Controller\Manage;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Bro\AppBundle\Entity\AjaxResponse;

use Bro\AppBundle\Entity\Strategy;
use Bro\AppBundle\Entity\Campain;
use Bro\AppBundle\Entity\Limit;

use Bro\AppBundle\Controller\Manage\ManageController;
use Bro\ApiBundle\Controller\YandexApiController;

use Bro\AppBundle\Form\Type\Manage\CampainType;
use Bro\AppBundle\Form\Type\Manage\CampainSettingsType;

class CampainsController extends ManageController{

	private $AjaxResponse;


	function __construct(){
		$this->AjaxResponse=AjaxResponse::getInstance();
	}

    public function addAction(Request $Request, $yandex_login){

        $EM=$this->getDoctrine()->getManager();
        $User = $this->getUser();
        $YandexApi = $this->get('yandex_api');

        $YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findUserRootYandexLoginsWithCampains($User->getId());
        $YandexLogin=$EM->getRepository('BroAppBundle:YandexLogin')->findFullOneByLogin($yandex_login, true);
        $NewCampain= new Campain();
        $YandexApi->setToken($YandexLogin->getToken());

        $yandex_login=$YandexLogin->getParentLogin()?$YandexLogin->getParentLogin()->getLogin():$YandexLogin->getLogin();
        $yandex_client=$YandexLogin->getParentLogin()?$YandexLogin->getLogin():false;
        $timeZones=$YandexApi->sendRequest('GetTimeZones', [], 'data');
        $regions=$YandexApi->sendRequest('GetRegions', [], 'data');
        $dayBudgetEnabled=false;

        //Смотрим есть ли возможность ставить лимит у других кампаний
        //к сожалению для первой кампании он соответственно будет всегда недоступен
        foreach($YandexLogin->getCampains() as $Campain){
            if($Campain->getDayBudgetEnabled()=='Yes'){
                $dayBudgetEnabled=true;
            break;
            }
        }
        $NewCampain->setStdParam('Login', $YandexLogin->getLogin())
            ->setStdParam('FIO', $User->getName())
            ->setStdParam(['EmailNotification', 'Email'], $User->getEmail())
            ->setStdParam('DayBudgetEnabled', $dayBudgetEnabled?'Yes':'No');

        $campaignParams=$NewCampain->getStdParams();


        $CampainForm=$this->createForm(new CampainType($campaignParams, false, $timeZones, false, false, false), null, ['validation_groups' => array('add_campain')]);


        //Рабочее время
        $workTime=[['days'=>[0, 1, 2, 3, 4], 'time'=>['from'=>['hours'=>'10', 'minutes'=>'00'],'to'=>['hours'=>'18', 'minutes'=>'00']]]];


        $daysHours=[
            1=>['title'=>'Пн.', 'active'=>true, 'hours'=>[0=>['active'=>true, 'coef'=>100], 1=>['active'=>true, 'coef'=>100], 2=>['active'=>true, 'coef'=>100], 3=>['active'=>true, 'coef'=>100], 4=>['active'=>true, 'coef'=>100], 5=>['active'=>true, 'coef'=>100], 6=>['active'=>true, 'coef'=>100], 7=>['active'=>true, 'coef'=>100], 8=>['active'=>true, 'coef'=>100], 9=>['active'=>true, 'coef'=>100], 10=>['active'=>true, 'coef'=>100], 11=>['active'=>true, 'coef'=>100], 12=>['active'=>true, 'coef'=>100], 13=>['active'=>true, 'coef'=>100], 14=>['active'=>true, 'coef'=>100], 15=>['active'=>true, 'coef'=>100], 16=>['active'=>true, 'coef'=>100], 17=>['active'=>true, 'coef'=>100], 18=>['active'=>true, 'coef'=>100], 19=>['active'=>true, 'coef'=>100], 20=>['active'=>true, 'coef'=>100], 21=>['active'=>true, 'coef'=>100], 22=>['active'=>true, 'coef'=>100], 23=>['active'=>true, 'coef'=>100]]],
            2=>['title'=>'Вт.', 'active'=>true, 'hours'=>[0=>['active'=>true, 'coef'=>100], 1=>['active'=>true, 'coef'=>100], 2=>['active'=>true, 'coef'=>100], 3=>['active'=>true, 'coef'=>100], 4=>['active'=>true, 'coef'=>100], 5=>['active'=>true, 'coef'=>100], 6=>['active'=>true, 'coef'=>100], 7=>['active'=>true, 'coef'=>100], 8=>['active'=>true, 'coef'=>100], 9=>['active'=>true, 'coef'=>100], 10=>['active'=>true, 'coef'=>100], 11=>['active'=>true, 'coef'=>100], 12=>['active'=>true, 'coef'=>100], 13=>['active'=>true, 'coef'=>100], 14=>['active'=>true, 'coef'=>100], 15=>['active'=>true, 'coef'=>100], 16=>['active'=>true, 'coef'=>100], 17=>['active'=>true, 'coef'=>100], 18=>['active'=>true, 'coef'=>100], 19=>['active'=>true, 'coef'=>100], 20=>['active'=>true, 'coef'=>100], 21=>['active'=>true, 'coef'=>100], 22=>['active'=>true, 'coef'=>100], 23=>['active'=>true, 'coef'=>100]]],
            3=>['title'=>'Ср.', 'active'=>true, 'hours'=>[0=>['active'=>true, 'coef'=>100], 1=>['active'=>true, 'coef'=>100], 2=>['active'=>true, 'coef'=>100], 3=>['active'=>true, 'coef'=>100], 4=>['active'=>true, 'coef'=>100], 5=>['active'=>true, 'coef'=>100], 6=>['active'=>true, 'coef'=>100], 7=>['active'=>true, 'coef'=>100], 8=>['active'=>true, 'coef'=>100], 9=>['active'=>true, 'coef'=>100], 10=>['active'=>true, 'coef'=>100], 11=>['active'=>true, 'coef'=>100], 12=>['active'=>true, 'coef'=>100], 13=>['active'=>true, 'coef'=>100], 14=>['active'=>true, 'coef'=>100], 15=>['active'=>true, 'coef'=>100], 16=>['active'=>true, 'coef'=>100], 17=>['active'=>true, 'coef'=>100], 18=>['active'=>true, 'coef'=>100], 19=>['active'=>true, 'coef'=>100], 20=>['active'=>true, 'coef'=>100], 21=>['active'=>true, 'coef'=>100], 22=>['active'=>true, 'coef'=>100], 23=>['active'=>true, 'coef'=>100]]],
            4=>['title'=>'Чт.', 'active'=>true, 'hours'=>[0=>['active'=>true, 'coef'=>100], 1=>['active'=>true, 'coef'=>100], 2=>['active'=>true, 'coef'=>100], 3=>['active'=>true, 'coef'=>100], 4=>['active'=>true, 'coef'=>100], 5=>['active'=>true, 'coef'=>100], 6=>['active'=>true, 'coef'=>100], 7=>['active'=>true, 'coef'=>100], 8=>['active'=>true, 'coef'=>100], 9=>['active'=>true, 'coef'=>100], 10=>['active'=>true, 'coef'=>100], 11=>['active'=>true, 'coef'=>100], 12=>['active'=>true, 'coef'=>100], 13=>['active'=>true, 'coef'=>100], 14=>['active'=>true, 'coef'=>100], 15=>['active'=>true, 'coef'=>100], 16=>['active'=>true, 'coef'=>100], 17=>['active'=>true, 'coef'=>100], 18=>['active'=>true, 'coef'=>100], 19=>['active'=>true, 'coef'=>100], 20=>['active'=>true, 'coef'=>100], 21=>['active'=>true, 'coef'=>100], 22=>['active'=>true, 'coef'=>100], 23=>['active'=>true, 'coef'=>100]]],
            5=>['title'=>'Пт.', 'active'=>true, 'hours'=>[0=>['active'=>true, 'coef'=>100], 1=>['active'=>true, 'coef'=>100], 2=>['active'=>true, 'coef'=>100], 3=>['active'=>true, 'coef'=>100], 4=>['active'=>true, 'coef'=>100], 5=>['active'=>true, 'coef'=>100], 6=>['active'=>true, 'coef'=>100], 7=>['active'=>true, 'coef'=>100], 8=>['active'=>true, 'coef'=>100], 9=>['active'=>true, 'coef'=>100], 10=>['active'=>true, 'coef'=>100], 11=>['active'=>true, 'coef'=>100], 12=>['active'=>true, 'coef'=>100], 13=>['active'=>true, 'coef'=>100], 14=>['active'=>true, 'coef'=>100], 15=>['active'=>true, 'coef'=>100], 16=>['active'=>true, 'coef'=>100], 17=>['active'=>true, 'coef'=>100], 18=>['active'=>true, 'coef'=>100], 19=>['active'=>true, 'coef'=>100], 20=>['active'=>true, 'coef'=>100], 21=>['active'=>true, 'coef'=>100], 22=>['active'=>true, 'coef'=>100], 23=>['active'=>true, 'coef'=>100]]],
            6=>['title'=>'Сб.', 'active'=>true, 'hours'=>[0=>['active'=>true, 'coef'=>100], 1=>['active'=>true, 'coef'=>100], 2=>['active'=>true, 'coef'=>100], 3=>['active'=>true, 'coef'=>100], 4=>['active'=>true, 'coef'=>100], 5=>['active'=>true, 'coef'=>100], 6=>['active'=>true, 'coef'=>100], 7=>['active'=>true, 'coef'=>100], 8=>['active'=>true, 'coef'=>100], 9=>['active'=>true, 'coef'=>100], 10=>['active'=>true, 'coef'=>100], 11=>['active'=>true, 'coef'=>100], 12=>['active'=>true, 'coef'=>100], 13=>['active'=>true, 'coef'=>100], 14=>['active'=>true, 'coef'=>100], 15=>['active'=>true, 'coef'=>100], 16=>['active'=>true, 'coef'=>100], 17=>['active'=>true, 'coef'=>100], 18=>['active'=>true, 'coef'=>100], 19=>['active'=>true, 'coef'=>100], 20=>['active'=>true, 'coef'=>100], 21=>['active'=>true, 'coef'=>100], 22=>['active'=>true, 'coef'=>100], 23=>['active'=>true, 'coef'=>100]]],
            7=>['title'=>'Вс.', 'active'=>true, 'hours'=>[0=>['active'=>true, 'coef'=>100], 1=>['active'=>true, 'coef'=>100], 2=>['active'=>true, 'coef'=>100], 3=>['active'=>true, 'coef'=>100], 4=>['active'=>true, 'coef'=>100], 5=>['active'=>true, 'coef'=>100], 6=>['active'=>true, 'coef'=>100], 7=>['active'=>true, 'coef'=>100], 8=>['active'=>true, 'coef'=>100], 9=>['active'=>true, 'coef'=>100], 10=>['active'=>true, 'coef'=>100], 11=>['active'=>true, 'coef'=>100], 12=>['active'=>true, 'coef'=>100], 13=>['active'=>true, 'coef'=>100], 14=>['active'=>true, 'coef'=>100], 15=>['active'=>true, 'coef'=>100], 16=>['active'=>true, 'coef'=>100], 17=>['active'=>true, 'coef'=>100], 18=>['active'=>true, 'coef'=>100], 19=>['active'=>true, 'coef'=>100], 20=>['active'=>true, 'coef'=>100], 21=>['active'=>true, 'coef'=>100], 22=>['active'=>true, 'coef'=>100], 23=>['active'=>true, 'coef'=>100]]]
        ];
        $hoursLabels=[0 =>['title'=>'00-01', 'active'=>true], 1 =>['title'=>'01-02', 'active'=>true], 2 =>['title'=>'02-03', 'active'=>true], 3 =>['title'=>'03-04', 'active'=>true], 4 =>['title'=>'04-05', 'active'=>true], 5 =>['title'=>'05-06', 'active'=>true], 6 =>['title'=>'06-07', 'active'=>true], 7 =>['title'=>'07-08', 'active'=>true], 8 =>['title'=>'08-09', 'active'=>true], 9 =>['title'=>'09-10', 'active'=>true], 10 =>['title'=>'10-11', 'active'=>true], 11 =>['title'=>'11-12', 'active'=>true], 12 =>['title'=>'12-13', 'active'=>true], 13 =>['title'=>'13-14', 'active'=>true], 14 =>['title'=>'14-15', 'active'=>true], 15 =>['title'=>'15-16', 'active'=>true], 16 =>['title'=>'16-17', 'active'=>true], 17 =>['title'=>'17-18', 'active'=>true], 18 =>['title'=>'18-19', 'active'=>true], 19 =>['title'=>'19-20', 'active'=>true], 20 =>['title'=>'20-21', 'active'=>true], 21 =>['title'=>'21-22', 'active'=>true], 22 =>['title'=>'22-23', 'active'=>true], 23 =>['title'=>'23-24', 'active'=>true]];
        $campaignActiveHours=['all'=>168, 'work_days'=>120];
        $active_regions=false;
        $active_regions_names=['active'=>[], 'excepted'=>[]];


        if ($Request->getMethod() == 'POST') {
            $CampainForm->handleRequest($Request);

            if($Request->isXmlHttpRequest()){

                if($CampainForm->isValid()){

                    //Устанавливаем значения для передачи яндексу
                    $this->createCampainParams($CampainForm, $campaignParams, false, $timeZones);

                    //Отправляем запрос
                    $add_result=$YandexApi->sendRequest('CreateOrUpdateCampaign', $campaignParams, 'data');
                    if(!$YandexApi->hasApiError()){

                        $StdStartegy=$EM->getRepository('BroAppBundle:Strategy')->findFirstOne();

                        if($add_result){
                            $geo='';
                            $contactInfo='';


                            //Сохраняем единый регион для первого объявления
                            if($CampainForm['Geo']->getData()){
                                $geo=implode(',', json_decode($CampainForm['Geo']->getData(), true));
                            }

                            //Сохраняем контакты для первого объявления
                            if($CampainForm['ContactInfo']['Status']->getData()){

                                $contactInfo=[
                                    'ContactPerson'=>$CampainForm['ContactInfo']['ContactPerson']->getData()?$CampainForm['ContactInfo']['ContactPerson']->getData():null,
                                    'Country'=>$CampainForm['ContactInfo']['Country']->getData(),
                                    'CountryCode'=>'+'.$CampainForm['ContactInfo']['CountryCode']->getData(),
                                    'City'=>$CampainForm['ContactInfo']['City']->getData(),
                                    'Street'=>$CampainForm['ContactInfo']['Street']->getData()?$CampainForm['ContactInfo']['Street']->getData():null,
                                    'House'=>$CampainForm['ContactInfo']['House']->getData()?$CampainForm['ContactInfo']['House']->getData():null,
                                    'Build'=>$CampainForm['ContactInfo']['Build']->getData()?$CampainForm['ContactInfo']['Build']->getData():null,
                                    'Apart'=>$CampainForm['ContactInfo']['Apart']->getData()?$CampainForm['ContactInfo']['Apart']->getData():null,
                                    'CityCode'=>$CampainForm['ContactInfo']['CityCode']->getData(),
                                    'Phone'=>$CampainForm['ContactInfo']['Phone']->getData(),
                                    'PhoneExt'=>$CampainForm['ContactInfo']['PhoneExt']->getData()?$CampainForm['ContactInfo']['PhoneExt']->getData():'',
                                    'CompanyName'=>$CampainForm['ContactInfo']['CompanyName']->getData(),
                                    'IMClient'=>$CampainForm['ContactInfo']['IMLogin']->getData()?$CampainForm['ContactInfo']['IMClient']->getData():null,
                                    'IMLogin'=>$CampainForm['ContactInfo']['IMLogin']->getData()?$CampainForm['ContactInfo']['IMLogin']->getData():'',
                                    'ExtraMessage'=>$CampainForm['ContactInfo']['ExtraMessage']->getData()?$CampainForm['ContactInfo']['ExtraMessage']->getData():null,
                                    'ContactEmail'=>$CampainForm['ContactInfo']['ContactEmail']->getData()?$CampainForm['ContactInfo']['ContactEmail']->getData():null,
                                    'WorkTime'=>$CampainForm['ContactInfo']['WorkTime']->getData(),
                                    'OGRN'=>$CampainForm['ContactInfo']['OGRN']->getData()?$CampainForm['ContactInfo']['OGRN']->getData():null
                                ];

                                $pointOnMap=$CampainForm['ContactInfo']['PointOnMap']->getData()?json_decode($CampainForm['ContactInfo']['PointOnMap']->getData(), true):null;
                                if($pointOnMap){
                                    ksort($pointOnMap);
                                    foreach($pointOnMap as &$coord){
                                        $coord=round($coord, 6);
                                    }
                                    unset($coord);
                                }
                                $contactInfo['PointOnMap']=$pointOnMap;
                                ksort($contactInfo);
                            }


                            //Сохраняем у нас
                            $NewCampain->fill($campaignParams)
                                ->setCampaignID($add_result)
                                ->setUser($User)
                                ->setYandexLogin($YandexLogin)
                                ->setDataStatus('active')
                                ->SetStrategy($StdStartegy)
                                ->setMaxPrice(0)
                                ->setRest(0)
                                ->setSum(0)
                                ->setShows(0)
                                ->setClicks(0)
                                ->setStatus('Черновик')
                                ->setStatusShow('Yes')
                                ->setStatusArchive('No')
                                ->setStatusModerate('New')
                                ->setIsActive('No')
                                ->setGeo($geo)
                                ->setContactInfo(json_encode($contactInfo))
                                ->setDayBudgetEnabled($dayBudgetEnabled)
                                ->SetStartDate(new \DateTime($campaignParams['StartDate']));

                            $EM->persist($NewCampain);
                            $EM->flush();
                            $this->AjaxResponse->setData(['url'=>$this->generateUrl('manage_ad_group_add', ['campain_id'=>$NewCampain->getId()])], 'json', 'ok');

                        }

                    } else {
                        $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                    }

                } else {
                    $this->setAjaxFromErrors('c_012', $CampainForm->getErrors(true), $this->AjaxResponse);
                }

                return $this->AjaxResponse->getResponse();
            }
        }



        return $this->render('BroAppBundle:Manage/Campain:add.html.twig', [
            'YandexLogins'=>$YandexLogins,
            'YandexLogin'=>$YandexLogin,
            'yandex_login'=>$yandex_login,
            'yandex_client'=>$yandex_client,
            'action'=>$this->generateUrl('manage_campain_add', ['yandex_login'=>$yandex_client?$yandex_client:$yandex_login]),
            'campaignParams'=>$campaignParams,
            'CampainForm'=>$CampainForm->createView(),
            'daysHours'=>$daysHours,
            'hoursLabels'=>$hoursLabels,
            'campaignActiveHours'=>$campaignActiveHours,
            'active_regions_names'=>$active_regions_names,
            'regionsTree'=>$this->get('bro.tree')->buildRegionsTree($regions, $active_regions),
            'workTime'=>$workTime
        ]);
    }


  public function editAction($campain_id, $yandex_login=false, $yandex_client=false){
    $Request=$this->getRequest();
		$EM=$this->getDoctrine()->getManager();
    $User = $this->getUser();
    $YandexApi = $this->get('yandex_api');


    $YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findUserRootYandexLoginsWithCampains($User->getId());
    $Campain=$EM->getRepository('BroAppBundle:Campain')->findOneById($campain_id);
    $YandexApi->setToken($Campain->getYandexLogin()->getToken());

    $campaignParams=$YandexApi->sendRequest('GetCampaignParams', ['CampaignID'=>$Campain->getCampaignID()]);
    $campaignGoals=$YandexApi->sendRequest('GetStatGoals', ['CampaignIDS'=>[$Campain->getCampaignID()]], 'data');
    $timeZones=$YandexApi->sendRequest('GetTimeZones', [], 'data');
    $banners=$YandexApi->sendRequest('GetBanners', ['CampaignIDS'=>[$Campain->getCampaignID()], 'FieldsNames'=>['Geo', 'ContactInfo', 'StatusArchive'], 'GetPhrases'=>'No'],  'data');
    $regions=$YandexApi->sendRequest('GetRegions', [], 'data');


    $active_regions=false;
    $active_contact_info=false;
    if(count($banners)){

      //Проверяем на единый регион для всех объявлений
      foreach($banners as $banner){
        if(!$active_regions){
          $active_regions=$banner['Geo'];
        } else if ($active_regions!=$banner['Geo']){
          $active_regions=false;
          break;
        }
      }

       //Проверяем на единый адрес для всех объявлений
      foreach($banners as $banner){
        //В отличии от единого региона
        //единые контакты определяются только по активным объявлениям
        //возможно в будующем это изменится
        //ПРОВЕРЯТЬ
        if($banner['StatusArchive']=='No'){
          if(isset($banner['ContactInfo'])){
            if(!$active_contact_info){
              $active_contact_info=$banner['ContactInfo'];

            } else if (json_encode($active_contact_info)!=json_encode($banner['ContactInfo'])){
              $active_contact_info=false;
              break;
            }
          } else {
            $active_contact_info=false;
            break;
          }
        }
      }

      $active_regions=explode(',', $active_regions);

    } else {
      $active_regions=explode(',', $Campain->getGeo());
      $active_contact_info=json_decode($Campain->getContactInfo(), true);
    }



    //Разбираем рабочее врмя
    $workTime=[['days'=>[0, 1, 2, 3, 4], 'time'=>['from'=>['hours'=>'10', 'minutes'=>'00'],'to'=>['hours'=>'18', 'minutes'=>'00']]]];
    if(isset($active_contact_info['WorkTime'])&&$active_contact_info['WorkTime']){
      $workTime=[];
      $workTimePeriods=array_chunk(explode(';', $active_contact_info['WorkTime']), 6);

      foreach($workTimePeriods as $workTimePeriod){
        $time_key=$workTimePeriod[2].$workTimePeriod[3].$workTimePeriod[4].$workTimePeriod[5];

        for($i=$workTimePeriod[0]; $i<=$workTimePeriod[1]; ++$i){
          $workTime[$time_key]['days'][]=$i;
        }

         $workTime[$time_key]['time']=['from'=>['hours'=>$workTimePeriod[2], 'minutes'=>$workTimePeriod[3]],
                                       'to'=>['hours'=>$workTimePeriod[4], 'minutes'=>$workTimePeriod[5]]];
      }
    }






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
    //sort($active_regions_names['active']);

    $daysHours=[1=>['title'=>'Пн.', 'active'=>false, 'hours'=>[0=>['active'=>false, 'coef'=>100], 1=>['active'=>false, 'coef'=>100], 2=>['active'=>false, 'coef'=>100], 3=>['active'=>false, 'coef'=>100], 4=>['active'=>false, 'coef'=>100], 5=>['active'=>false, 'coef'=>100], 6=>['active'=>false, 'coef'=>100], 7=>['active'=>false, 'coef'=>100], 8=>['active'=>false, 'coef'=>100], 9=>['active'=>false, 'coef'=>100], 10=>['active'=>false, 'coef'=>100], 11=>['active'=>false, 'coef'=>100], 12=>['active'=>false, 'coef'=>100], 13=>['active'=>false, 'coef'=>100], 14=>['active'=>false, 'coef'=>100], 15=>['active'=>false, 'coef'=>100], 16=>['active'=>false, 'coef'=>100], 17=>['active'=>false, 'coef'=>100], 18=>['active'=>false, 'coef'=>100], 19=>['active'=>false, 'coef'=>100], 20=>['active'=>false, 'coef'=>100], 21=>['active'=>false, 'coef'=>100], 22=>['active'=>false, 'coef'=>100], 23=>['active'=>false, 'coef'=>100]]],
                2=>['title'=>'Вт.', 'active'=>false, 'hours'=>[0=>['active'=>false, 'coef'=>100], 1=>['active'=>false, 'coef'=>100], 2=>['active'=>false, 'coef'=>100], 3=>['active'=>false, 'coef'=>100], 4=>['active'=>false, 'coef'=>100], 5=>['active'=>false, 'coef'=>100], 6=>['active'=>false, 'coef'=>100], 7=>['active'=>false, 'coef'=>100], 8=>['active'=>false, 'coef'=>100], 9=>['active'=>false, 'coef'=>100], 10=>['active'=>false, 'coef'=>100], 11=>['active'=>false, 'coef'=>100], 12=>['active'=>false, 'coef'=>100], 13=>['active'=>false, 'coef'=>100], 14=>['active'=>false, 'coef'=>100], 15=>['active'=>false, 'coef'=>100], 16=>['active'=>false, 'coef'=>100], 17=>['active'=>false, 'coef'=>100], 18=>['active'=>false, 'coef'=>100], 19=>['active'=>false, 'coef'=>100], 20=>['active'=>false, 'coef'=>100], 21=>['active'=>false, 'coef'=>100], 22=>['active'=>false, 'coef'=>100], 23=>['active'=>false, 'coef'=>100]]],
                3=>['title'=>'Ср.', 'active'=>false, 'hours'=>[0=>['active'=>false, 'coef'=>100], 1=>['active'=>false, 'coef'=>100], 2=>['active'=>false, 'coef'=>100], 3=>['active'=>false, 'coef'=>100], 4=>['active'=>false, 'coef'=>100], 5=>['active'=>false, 'coef'=>100], 6=>['active'=>false, 'coef'=>100], 7=>['active'=>false, 'coef'=>100], 8=>['active'=>false, 'coef'=>100], 9=>['active'=>false, 'coef'=>100], 10=>['active'=>false, 'coef'=>100], 11=>['active'=>false, 'coef'=>100], 12=>['active'=>false, 'coef'=>100], 13=>['active'=>false, 'coef'=>100], 14=>['active'=>false, 'coef'=>100], 15=>['active'=>false, 'coef'=>100], 16=>['active'=>false, 'coef'=>100], 17=>['active'=>false, 'coef'=>100], 18=>['active'=>false, 'coef'=>100], 19=>['active'=>false, 'coef'=>100], 20=>['active'=>false, 'coef'=>100], 21=>['active'=>false, 'coef'=>100], 22=>['active'=>false, 'coef'=>100], 23=>['active'=>false, 'coef'=>100]]],
                4=>['title'=>'Чт.', 'active'=>false, 'hours'=>[0=>['active'=>false, 'coef'=>100], 1=>['active'=>false, 'coef'=>100], 2=>['active'=>false, 'coef'=>100], 3=>['active'=>false, 'coef'=>100], 4=>['active'=>false, 'coef'=>100], 5=>['active'=>false, 'coef'=>100], 6=>['active'=>false, 'coef'=>100], 7=>['active'=>false, 'coef'=>100], 8=>['active'=>false, 'coef'=>100], 9=>['active'=>false, 'coef'=>100], 10=>['active'=>false, 'coef'=>100], 11=>['active'=>false, 'coef'=>100], 12=>['active'=>false, 'coef'=>100], 13=>['active'=>false, 'coef'=>100], 14=>['active'=>false, 'coef'=>100], 15=>['active'=>false, 'coef'=>100], 16=>['active'=>false, 'coef'=>100], 17=>['active'=>false, 'coef'=>100], 18=>['active'=>false, 'coef'=>100], 19=>['active'=>false, 'coef'=>100], 20=>['active'=>false, 'coef'=>100], 21=>['active'=>false, 'coef'=>100], 22=>['active'=>false, 'coef'=>100], 23=>['active'=>false, 'coef'=>100]]],
                5=>['title'=>'Пт.', 'active'=>false, 'hours'=>[0=>['active'=>false, 'coef'=>100], 1=>['active'=>false, 'coef'=>100], 2=>['active'=>false, 'coef'=>100], 3=>['active'=>false, 'coef'=>100], 4=>['active'=>false, 'coef'=>100], 5=>['active'=>false, 'coef'=>100], 6=>['active'=>false, 'coef'=>100], 7=>['active'=>false, 'coef'=>100], 8=>['active'=>false, 'coef'=>100], 9=>['active'=>false, 'coef'=>100], 10=>['active'=>false, 'coef'=>100], 11=>['active'=>false, 'coef'=>100], 12=>['active'=>false, 'coef'=>100], 13=>['active'=>false, 'coef'=>100], 14=>['active'=>false, 'coef'=>100], 15=>['active'=>false, 'coef'=>100], 16=>['active'=>false, 'coef'=>100], 17=>['active'=>false, 'coef'=>100], 18=>['active'=>false, 'coef'=>100], 19=>['active'=>false, 'coef'=>100], 20=>['active'=>false, 'coef'=>100], 21=>['active'=>false, 'coef'=>100], 22=>['active'=>false, 'coef'=>100], 23=>['active'=>false, 'coef'=>100]]],
                6=>['title'=>'Сб.', 'active'=>false, 'hours'=>[0=>['active'=>false, 'coef'=>100], 1=>['active'=>false, 'coef'=>100], 2=>['active'=>false, 'coef'=>100], 3=>['active'=>false, 'coef'=>100], 4=>['active'=>false, 'coef'=>100], 5=>['active'=>false, 'coef'=>100], 6=>['active'=>false, 'coef'=>100], 7=>['active'=>false, 'coef'=>100], 8=>['active'=>false, 'coef'=>100], 9=>['active'=>false, 'coef'=>100], 10=>['active'=>false, 'coef'=>100], 11=>['active'=>false, 'coef'=>100], 12=>['active'=>false, 'coef'=>100], 13=>['active'=>false, 'coef'=>100], 14=>['active'=>false, 'coef'=>100], 15=>['active'=>false, 'coef'=>100], 16=>['active'=>false, 'coef'=>100], 17=>['active'=>false, 'coef'=>100], 18=>['active'=>false, 'coef'=>100], 19=>['active'=>false, 'coef'=>100], 20=>['active'=>false, 'coef'=>100], 21=>['active'=>false, 'coef'=>100], 22=>['active'=>false, 'coef'=>100], 23=>['active'=>false, 'coef'=>100]]],
                7=>['title'=>'Вс.', 'active'=>false, 'hours'=>[0=>['active'=>false, 'coef'=>100], 1=>['active'=>false, 'coef'=>100], 2=>['active'=>false, 'coef'=>100], 3=>['active'=>false, 'coef'=>100], 4=>['active'=>false, 'coef'=>100], 5=>['active'=>false, 'coef'=>100], 6=>['active'=>false, 'coef'=>100], 7=>['active'=>false, 'coef'=>100], 8=>['active'=>false, 'coef'=>100], 9=>['active'=>false, 'coef'=>100], 10=>['active'=>false, 'coef'=>100], 11=>['active'=>false, 'coef'=>100], 12=>['active'=>false, 'coef'=>100], 13=>['active'=>false, 'coef'=>100], 14=>['active'=>false, 'coef'=>100], 15=>['active'=>false, 'coef'=>100], 16=>['active'=>false, 'coef'=>100], 17=>['active'=>false, 'coef'=>100], 18=>['active'=>false, 'coef'=>100], 19=>['active'=>false, 'coef'=>100], 20=>['active'=>false, 'coef'=>100], 21=>['active'=>false, 'coef'=>100], 22=>['active'=>false, 'coef'=>100], 23=>['active'=>false, 'coef'=>100]]]
               ];
    $hoursLabels=[0 =>['title'=>'00-01', 'active'=>false], 1 =>['title'=>'01-02', 'active'=>false], 2 =>['title'=>'02-03', 'active'=>false], 3 =>['title'=>'03-04', 'active'=>false], 4 =>['title'=>'04-05', 'active'=>false], 5 =>['title'=>'05-06', 'active'=>false], 6 =>['title'=>'06-07', 'active'=>false], 7 =>['title'=>'07-08', 'active'=>false], 8 =>['title'=>'08-09', 'active'=>false], 9 =>['title'=>'09-10', 'active'=>false], 10 =>['title'=>'10-11', 'active'=>false], 11 =>['title'=>'11-12', 'active'=>false], 12 =>['title'=>'12-13', 'active'=>false], 13 =>['title'=>'13-14', 'active'=>false], 14 =>['title'=>'14-15', 'active'=>false], 15 =>['title'=>'15-16', 'active'=>false], 16 =>['title'=>'16-17', 'active'=>false], 17 =>['title'=>'17-18', 'active'=>false], 18 =>['title'=>'18-19', 'active'=>false], 19 =>['title'=>'19-20', 'active'=>false], 20 =>['title'=>'20-21', 'active'=>false], 21 =>['title'=>'21-22', 'active'=>false], 22 =>['title'=>'22-23', 'active'=>false], 23 =>['title'=>'23-24', 'active'=>false]];
    $hours_with_coefs=false;

    //Помечаем активные дни и ставим их коэфициенты
    $campaignActiveHours=['all'=>0, 'work_days'=>0];
    //Идем по массиву TimeTarget, его элементы это дни
    //или группы дней с одинаковыми данными
    foreach($campaignParams['TimeTarget']['DaysHours'] as $key=>$campaignDaysHours){
      //Идем по дням в TimeTarget
      foreach($campaignDaysHours['Days'] as $campaignDay){

        //Идем по часам соответствующего дня в уже нормализованном массиве
        //если для него существует значение в TimeTarget для этого дня
        //то момечаем его активным и работаем со счетчиками
        $i=0;
        foreach($daysHours[$campaignDay]['hours'] as $k=>&$hour){
          $campaignHourKey=array_search($k, $campaignDaysHours['Hours']);
          if($campaignHourKey!==false){
            $hour['active']=true;
            $hour['coef']=$campaignDaysHours['BidCoefs'][$campaignHourKey];
            if($campaignDaysHours['BidCoefs'][$campaignHourKey]<100){
              $hours_with_coefs=true;
            }
            ++$campaignActiveHours['all'];
            if($campaignDay!=6&&$campaignDay!=7){
              ++$campaignActiveHours['work_days'];
            }
            ++$i;
          }
        }
        unset($hour);

        //Если все часы активны, помечаем день активным
        if($i==count($daysHours[$campaignDay]['hours'])){
          $daysHours[$campaignDay]['active']=true;
        }
      }
    }


    //Ищем часы которые активны во все дни
    foreach($hoursLabels as $key=>&$hoursLabel){
      $i=0;
      foreach($daysHours as $dayHours){
        if($dayHours['hours'][$key]['active']){
          ++$i;
        }
      }
      if($i==count($daysHours)){
        $hoursLabel['active']=true;
      }
    }
    unset($hoursLabel);




    $CampainForm=$this->createForm(new CampainType($campaignParams, $campaignGoals, $timeZones, $hours_with_coefs, $active_regions, $active_contact_info), null, ['validation_groups' => array('edit_campain')]);

    if ($Request->getMethod() == 'POST') {
      $CampainForm->handleRequest($Request);

      if($Request->isXmlHttpRequest()){

        if($CampainForm->isValid()){

          //Устанавливаем значения для передачи яндексу
          $this->createCampainParams($CampainForm, $campaignParams, $campaignGoals, $timeZones);


          //Устанавливаем единый регион для объявлений
          $new_active_regions=json_decode($CampainForm['Geo']->getData(), true);
          //Сохраняем единый регион для первого объявления
          $Campain->setGeo(implode(',', json_decode($CampainForm['Geo']->getData(), true)));
         /* if(is_array($active_regions)){
            sort($active_regions);
          }
          sort($new_active_regions);*/


          $new_contact_info=[];
          if($CampainForm['ContactInfo']['Status']->getData()){
            $new_contact_info=['ContactPerson'=>$CampainForm['ContactInfo']['ContactPerson']->getData()?$CampainForm['ContactInfo']['ContactPerson']->getData():null,
                               'Country'=>$CampainForm['ContactInfo']['Country']->getData(),
                               'CountryCode'=>'+'.$CampainForm['ContactInfo']['CountryCode']->getData(),
                               'City'=>$CampainForm['ContactInfo']['City']->getData(),
                               'Street'=>$CampainForm['ContactInfo']['Street']->getData()?$CampainForm['ContactInfo']['Street']->getData():null,
                               'House'=>$CampainForm['ContactInfo']['House']->getData()?$CampainForm['ContactInfo']['House']->getData():null,
                               'Build'=>$CampainForm['ContactInfo']['Build']->getData()?$CampainForm['ContactInfo']['Build']->getData():null,
                               'Apart'=>$CampainForm['ContactInfo']['Apart']->getData()?$CampainForm['ContactInfo']['Apart']->getData():null,
                               'CityCode'=>$CampainForm['ContactInfo']['CityCode']->getData(),
                               'Phone'=>$CampainForm['ContactInfo']['Phone']->getData(),
                               'PhoneExt'=>$CampainForm['ContactInfo']['PhoneExt']->getData()?$CampainForm['ContactInfo']['PhoneExt']->getData():'',
                               'CompanyName'=>$CampainForm['ContactInfo']['CompanyName']->getData(),
                               'IMClient'=>$CampainForm['ContactInfo']['IMLogin']->getData()?$CampainForm['ContactInfo']['IMClient']->getData():null,
                               'IMLogin'=>$CampainForm['ContactInfo']['IMLogin']->getData()?$CampainForm['ContactInfo']['IMLogin']->getData():'',
                               'ExtraMessage'=>$CampainForm['ContactInfo']['ExtraMessage']->getData()?$CampainForm['ContactInfo']['ExtraMessage']->getData():null,
                               'ContactEmail'=>$CampainForm['ContactInfo']['ContactEmail']->getData()?$CampainForm['ContactInfo']['ContactEmail']->getData():null,
                               'WorkTime'=>$CampainForm['ContactInfo']['WorkTime']->getData(),
                               'OGRN'=>$CampainForm['ContactInfo']['OGRN']->getData()?$CampainForm['ContactInfo']['OGRN']->getData():null
                              ];

              $pointOnMap=$CampainForm['ContactInfo']['PointOnMap']->getData()?json_decode($CampainForm['ContactInfo']['PointOnMap']->getData(), true):null;
              if($pointOnMap){
                ksort($pointOnMap);
                foreach($pointOnMap as &$coord){
                  $coord=round($coord, 6);
                }
                unset($coord);
              }
              $new_contact_info['PointOnMap']=$pointOnMap;

              //Сохраняем контакты для первого объявления
              $Campain->setContactInfo(json_encode($new_contact_info));

            if(is_array($active_contact_info)){
              ksort($active_contact_info);
              if(isset($active_contact_info['PointOnMap'])){
                ksort($active_contact_info['PointOnMap']);
              }
            }
            ksort($new_contact_info);
          }


          //Если изменен регион
          //или изменились контактные данные
          //ВНИМАНИЕ. За операцию снимаются балы и она лимиторованна потом доделать проверкку
          if($active_regions!=$new_active_regions||$active_contact_info!=$new_contact_info){

            $banners=$YandexApi->sendRequest('GetBanners', ['CampaignIDS'=>[$Campain->getCampaignID()], 'FieldsNames'=>['BannerID', 'CampaignID', 'ContactInfo', 'StatusArchive', 'Title', 'Text', 'Href', 'Geo']],  'data');
            if($banners){
              $active_banners=[];
              $archived_banners=[];
              $archived_banners_ids=[];


              //Так как в текущей версии апи редактировать архивные объявления нельзя
              //то обновляем их отдельно
              foreach($banners as $banner){

                if($active_regions!=$new_active_regions){
                  $banner['Geo']=implode(',', $new_active_regions);
                }
                /**/

                if(count($new_contact_info)&&($active_contact_info!=$new_contact_info)){
                  $banner['ContactInfo']=$new_contact_info;
                }

                if($banner['StatusArchive']=='Yes'){
                  $archived_banners[]=$banner;
                  $archived_banners_ids[]=$banner['BannerID'];
                } else {
                  $active_banners[]=$banner;
                }

              }

              $YandexApi->sendRequest('CreateOrUpdateBanners', $active_banners, 'data');

              //Сохраняем в базе
              if($active_regions!=$new_active_regions){
                foreach($Campain->GetAdGroups() as $AdGroup){
                  $AdGroup->setGeo($banner['Geo']);
                }
              }

              if(count($new_contact_info)&&($active_contact_info!=$new_contact_info)){


                foreach($Campain->GetBanners() as $Banner){
                  $Banner->setContactInfo(json_encode($new_contact_info));
                }
              }


              //Сначала разархивируем их
              //изменим регион
              //и архивируем обратно
              if(count($archived_banners)){
                $YandexApi->sendRequest('UnArchiveBanners', ['BannerIDS'=>$archived_banners_ids],  'data');
                $YandexApi->sendRequest('CreateOrUpdateBanners', $archived_banners, 'data');
                $YandexApi->sendRequest('ArchiveBanners', ['BannerIDS'=>$archived_banners_ids],  'data');
              }

            }




          }

          //Отправляем запрос
          $YandexApi->sendRequest('CreateOrUpdateCampaign', $campaignParams);
          if(!$YandexApi->hasApiError()){

            //Сохраняем у нас
            $Campain->fill($campaignParams);
            $EM->flush();

            $this->AjaxResponse->setStatus('ok');

          } else {

            $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
          }
        } else {
          $this->setAjaxFromErrors('c_012', $CampainForm->getErrors(true), $this->AjaxResponse);
        }

        return $this->AjaxResponse->getResponse();
      }

    }

    $action=$this->generateUrl('manage_campain_edit', ['campain_id'=>$campain_id, 'yandex_login'=>$yandex_login]);
    if($yandex_client){
      $action=$this->generateUrl('manage_client_campain_edit', ['campain_id'=>$campain_id, 'yandex_login'=>$yandex_login, 'yandex_client'=>$yandex_client]);
    }

    if($Campain&&$campaignParams){
      return $this->render('BroAppBundle:Manage/Campain:edit.html.twig', array('YandexLogins'=>$YandexLogins,
                                                                               'yandex_login'=>$yandex_login,
                		                                                           'yandex_client'=>$yandex_client,
                                                                               'campain_id'=>$Campain->getId(),
                                                                               'Campain'=>$Campain,
                                                                               'CampainForm'=>$CampainForm->createView(),
                                                                               'campaignParams'=>$campaignParams,
                                                                               'daysHours'=>$daysHours,
                                                                               'hoursLabels'=>$hoursLabels,
                                                                               'campaignActiveHours'=>$campaignActiveHours,
                                                                               'active_regions_names'=>$active_regions_names,
                                                                               'regionsTree'=>$this->get('bro.tree')->buildRegionsTree($regions, $active_regions),
                                                                               'workTime'=>$workTime,
                                                                               'action'=>$action));
    }
  }




  public function editCampainStrategyAction($campain_id, $strategy_id=false, $filter=false, $banner_id=false, $render_response=true){
    $Request=$this->getRequest();
		$EM=$this->getDoctrine()->getManager();
		$Campains=$EM->getRepository('BroAppBundle:Campain')->findFullById($campain_id, $filter, $banner_id);

    if($Request->request->has('render_response')){
      $render_response=$Request->request->get('render_response');
    }

    if(!$strategy_id){
      $strategy_id=$Request->request->get('strategy_id');
    }
    $Strategy=$EM->getRepository('BroAppBundle:Strategy')->findOneById($strategy_id);

		if ($Request->getMethod() == 'POST') {

			if($Strategy){

        foreach($Campains as &$Campain){
  				if($EM->getRepository('BroAppBundle:Campain')->checkUserAcces($Campain->getId(), $this->getUser())){

            $Campain->setStrategy($Strategy);

            if(count($Campain->GetAdGroups())){
              foreach($Campain->GetAdGroups() as $AdGroup){
                $AdGroup->setStrategy($Strategy);

                if(count($AdGroup->GetPhrases())){
                  foreach($AdGroup->GetPhrases() as $Phrase){
                    $Phrase->setStrategy($Strategy);
                  }
                }
              }
            }
  				} else {
  					$this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
            break;
  				}
        }
        unset($Campain);


        //Если нет ошибок отправляем в базу и отдаем ответ
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

  public function editCampainMaxPriceAction($campain_id, $max_price=false, $filter=false, $banner_id=false, $render_response=true){
    $Request=$this->getRequest();
		$EM=$this->getDoctrine()->getManager();

    if($max_price===false){
      $max_price=$Request->request->get('max_price');
    }

    if($Request->request->has('render_response')){
      $render_response=$Request->request->get('render_response');
    }

    //$referer_params = $this->get('router')->match(str_replace('http://'.$_SERVER['HTTP_HOST'], '', $_SERVER['HTTP_REFERER']));
    $Campains=$EM->getRepository('BroAppBundle:Campain')->findFullById($campain_id, $filter, $banner_id);

		if ($Request->getMethod() == 'POST') {

      foreach($Campains as $Campain){
    		if($EM->getRepository('BroAppBundle:Campain')->checkUserAcces($Campain->getId(), $this->getUser())){

          $Campain->setMaxPrice($max_price);

          if(count($Campain->GetAdGroups())){
            foreach($Campain->GetAdGroups() as $AdGroup){
              $AdGroup->setMaxPrice($max_price);

              if(count($AdGroup->GetPhrases())){
                foreach($AdGroup->GetPhrases() as $Phrase){
                  $Phrase->setMaxPrice($max_price);
                }
              }
            }
          }
    		} else {
    			$this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
          break;
    		}
      }

      //Если нет ошибок отправляем в базу и отдаем ответ
      if(!$this->AjaxResponse->getHasErrors()){
        $EM->flush();
        //Если нужно вернуть рендер
        if($render_response){
          /*$this->AjaxResponse->setData(array('workflow'=>$this->campainAction((isset($referer_params['filter'])?$referer_params['filter']:false),
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


    function settingsAction($campain_id){
        $Request=$this->getRequest();
        $EM=$this->getDoctrine()->getManager();
        $YandexApi = $this->get('yandex_api5');

        $Campain=$EM->getRepository('BroAppBundle:Campain')->findOneByIdWithSettings($campain_id);
        $YandexApi->setToken5($Campain->getYandexLogin()->getToken(), $Campain->getYandexLogin());

        $this->updateSumLimitFromYandex($Campain, $YandexApi);

        $CampainSettingsForm=$this->createForm(new CampainSettingsType($Campain), $Campain);
        $EM->flush();

        if ($Request->getMethod() == 'POST') {

            $CampainSettingsForm->handleRequest($Request);
            $EM->persist($Campain);


            //Сьрасываем автостарты если они не нужны
            if($Campain->getAutoStart()&&$Campain->getLimits()){
                $autoStartLimits=false;
                foreach($Campain->getLimits() as $Limit){
                    if($Limit->getResume()){
                        $autoStartLimits=true;
                    }
                }
                if(!$autoStartLimits){
                    $Campain->setAutoStart(false);
                    $Campain->setAutoStartDate('0000-00-00');
                }
            }

            //Устанавливаем дневной бюджет на яндексе
            if($Campain->getDayBudgetEnabled()==='Yes'
            &&isset($Campain->getLimits()['sum'])){

                //Получается лимит нельзя отключить
                //&&$Campain->getLimits()['sum']->getValue()>0){

                $Limit=$Campain->getLimits()['sum'];


                //$campain_params=$YandexApi->sendRequest('GetCampaignParams', ['CampaignID'=>$Campain->getCampaignID()]);
                $request_data=[
                    'SelectionCriteria'=>['Ids'=>[$Campain->getCampaignID()]],
                    'FieldNames'=>['Id', 'DailyBudget'],
                ];
                $campain_params=$YandexApi->send5Request('campaigns', 'get', $request_data, [], 'Campaigns');



                if($Limit->getTime()==='daily'){
                    $day_budget=$Limit->getValue();
                } else if ($Limit->getTime()==='weekly'){
                    $day_budget=$Limit->getValue()/7;
                } else if($Limit->getTime()==='monthly'){
                    $day_budget=$Limit->getValue()/31;
                }

                if(!$YandexApi->hasApiError()){
                    $campain_params=$campain_params[0];
                    $campain_params['DailyBudget']['Amount']=round($day_budget, 2)*1000000;

                    //$YandexApi->sendRequest('CreateOrUpdateCampaign', $campain_params);
                    $request_data=[
                        'Campaigns'=>[$campain_params]
                    ];
                    $YandexApi->send5Request('campaigns', 'update', $request_data, [], 'UpdateResults');


                    if($YandexApi->hasApiError()){
                        $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                    } else {
                        $EM->flush();
                    }

                } else {
                    $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                }
            } else {
                $EM->flush();
            }

            $this->AjaxResponse->setStatus('ok');
            return $this->AjaxResponse->getResponse();
        }


        return $this->render('BroAppBundle:Manage:campain_settings.html.twig', array('Campain'=>$Campain,
                                                                     'campain_settings_form'=>$CampainSettingsForm->createView()));
    }


    //Обновляем лимит суммы от яндекса на члучай если его поменяли с той стороны
    function updateSumLimitFromYandex(&$Campain, $YandexApi=false){

        if(!$YandexApi){
            $YandexApi = $this->get('yandex_api5');
            $YandexApi->setToken5($Campain->getYandexLogin()->getToken(), $Campain->getYandexLogin());
        }

        //Если лимит регулируется яндексом, хагружаем его
        //if($Campain->getDayBudgetEnabled()==='Yes'){

            //$campain_params=$YandexApi->sendRequest('GetCampaignParams', ['CampaignID'=>$Campain->getCampaignID()]);

            $request_data=[
                'SelectionCriteria'=>['Ids'=>[$Campain->getCampaignID()]],
                'FieldNames'=>['DailyBudget'],
            ];
            $campain_params=$YandexApi->send5Request('campaigns', 'get', $request_data, [], 'Campaigns');

            if(!$YandexApi->hasApiError()){

                $campain_params=$campain_params[0];

                //Если нет добавляем
                if(!isset($Campain->getLimits()['sum'])){

                    $Limit=new Limit();
                        $Limit->setCampain($Campain)
                        ->setValue($campain_params['DailyBudget']['Amount']/1000000)
                        ->setTime('daily')
                        ->setType('sum')
                        ->setStop(true)
                        ->setNote(true)
                        ->setResume(true);
                        $Campain->addLimit($Limit, 'sum');

                //Если нет меняем значение
                } else {


                    $Limit=$Campain->getLimits()['sum'];
                    $day_budget=$campain_params['DailyBudget']['Amount']/1000000;

                    if ($Limit->getTime()==='weekly'){
                        $day_budget=$day_budget*7;
                    } else if($Limit->getTime()==='monthly'){
                        $day_budget=$day_budget*31;
                    }

                    $Limit->setValue(round($day_budget, 2));
                }

                return true;
            }
        //}

        return false;
    }

    function autoStartAction(){

        $Request=$this->getRequest();
        $EM=$this->getDoctrine()->getManager();
        $YandexApi = $this->get('yandex_api5');

        $start = microtime(true);
        $start_memory=memory_get_usage();
        $end_memory=$start_memory;


        $Campains=$EM->getRepository('BroAppBundle:Campain')->findBy(['autoStart'=>true, 'autoStartDate'=>new \DateTime('now')]);
        //dump($Campains);

        if($Campains){
            foreach($Campains as &$Campain){
                $YandexApi->setToken5($Campain->getYandexLogin()->getToken(), $Campain->getYandexLogin());

                //запуск
                if($Campain->getStatusArchive()==='Yes'){
                    $request_data=['SelectionCriteria'=>['Ids'=>[$Campain->getCampaignID()]]];
                    $YandexApi->send5Request('campaigns', 'unarchive', $request_data, [],'UnarchiveResults');
                    //$YandexApi->sendRequest('UnArchiveCampaign', ['CampaignID'=>$Campain->getCampaignID()]);

                }

                if(!$YandexApi->hasApiError()){
                    $Campain->setStatusArchive('No');

                    //$YandexApi->sendRequest('ResumeCampaign', ['CampaignID'=>$Campain->getCampaignID()]);
                    $request_data=['SelectionCriteria'=>['Ids'=>[$Campain->getCampaignID()]]];
                    $YandexApi->send5Request('campaigns', 'resume', $request_data, [], 'ResumeResults');

                    if(!$YandexApi->hasApiError()){
                        $Campain->setStatusShow('Yes');
                        $Campain->setAutoStart(false);
                        $Campain->setAutoStartDate('0000-00-00');
                    }
                }

            }
        }

        $EM->flush();
        $EM->clear();

        $end_time=(microtime(true) - $start);
        $end_memory=memory_get_usage();

        return $this->render('BroAppBundle:StaticElements:test.html.twig', array('time'=>$end_time, 'memory'=>$end_memory-$start_memory));

    }


    function switchAutoStartAction($campain_id){
        $Request=$this->getRequest();
        $EM=$this->getDoctrine()->getManager();

        $Campain=$EM->getRepository('BroAppBundle:Campain')->findOneById($campain_id);

        if($Request->getMethod() == 'POST'&&$Request->isXmlHttpRequest()) {

            if($EM->getRepository('BroAppBundle:Campain')->checkUserAcces($Campain->getId(), $this->getUser())){

                if($Request->request->get('value')){
                    $Campain->setAutoStart(true);
                    $Campain->setAutoStartDate($Request->request->get('autoStartDate'));
                } else {
                    $Campain->setAutoStart(false);
                    $Campain->setAutoStartDate('0000-00-00');
                }

                $EM->flush();
                $this->AjaxResponse->setStatus('ok');

            } else {
                $this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
            }

        }

     return $this->AjaxResponse->getResponse();
    }


    //А если лимит увеличен после остановки???? - нахуй слишком много подводных камней
    //Убирать флаги автовключения при редактировании формы лимитов
    function regularCkeckLimitsAction(){

        $Request=$this->getRequest();
        $EM=$this->getDoctrine()->getManager();
        $YandexApi = $this->get('yandex_api');
        $YandexApi5 = $this->get('yandex_api5');
        $Logger = $this->get('monolog.logger.bro');

        $used_limits=array();
        $auto_start_limits=array();

        $start = microtime(true);
        $start_memory=memory_get_usage();
        $end_memory=$start_memory;

        //Именно в таком порядке,
        //так как самый длинный сработавший лимит должен зававать дату автостарта
        $campains_ids_groups=[
            'daily'=>['days'=>1, 'start_date'=>date('Y-m-d'), 'auto_start_date'=>date('Y-m-d', strtotime('tomorrow')), 'items'=>[] ],
            'weekly'=>['days'=>7, 'start_date'=>date('Y-m-d', strtotime('last monday', strtotime('tomorrow'))), 'auto_start_date'=>date('Y-m-d', strtotime('Monday next week', strtotime('yesterday'))), 'items'=>[] ],
            'monthly'=>['days'=>date('t'), 'start_date'=>date('Y-m-d', strtotime('first day of this month')), 'auto_start_date'=>date('Y-m-d', strtotime('first day of next month')), 'items'=>[] ]
        ];


        //Может попробовать оптимизоровать и выбрать 3 запросами кампании соответствующих групп?
        $Campains=$EM->getRepository('BroAppBundle:Campain')->findWorking(array('StatusShow'=>'Yes'), true, 'CampaignID');

        $tokenData=[];
        //Группируем ид кампаний по времени лимита и токену
        if($Campains){
            foreach($Campains as $Campain){
                $this->updateSumLimitFromYandex($Campain);

                if($Campain->getLimits()){
                    foreach($Campain->getLimits() as $Limit){
                        $campains_ids_groups[$Limit->getTime()]['items'][$Campain->getYandexLogin()->getToken()][]=$Campain->getCampaignID();
                        $tokenData[$Campain->getYandexLogin()->getToken()]=[
                            'token'=>$Campain->getYandexLogin()->getToken(),
                            'login'=>$Campain->getYandexLogin()
                        ];
                    }
                }
            }

            $EM->flush();
        }

        $LimitMessage=$this->get('bro.mailer')->getService()->createMessage()
                        ->setSubject('['.$this->container->getParameter('site').'] Достигнуто ограничение по кампании')
                        ->setFrom($this->container->getParameter('sender_email'));


        //print_r($campains_ids_groups);
        //Идем по временным группам
        foreach($campains_ids_groups as $group_time=>$campains_ids_group){

            $max_pack_items=floor(1000/$campains_ids_group['days']);
            $total_stat=array();

            $YandexApi->setToken($Campain->getYandexLogin()->getToken());
            $YandexApi5->setToken5($Campain->getYandexLogin()->getToken(), $Campain->getYandexLogin());

            //Идем по группам кампаний привязаных к ооному токену
            foreach($campains_ids_group['items'] as $key=>$campains_ids_group_items){

                $YandexApi->setToken($key);
                $YandexApi5->setToken5($tokenData[$key]['token'], $tokenData[$key]['login']);

                //Разбиваем на подгруппы айдишек с учетом ограничения яндекса на запрос
                $campains_ids_packs=array_chunk($campains_ids_group_items, $max_pack_items);
                foreach($campains_ids_packs as $campains_ids_pack){

                    $stats=$YandexApi->sendRequest('GetSummaryStat', [
                        'CampaignIDS'=>$campains_ids_pack,
                        'StartDate'=>$campains_ids_group['start_date'],
                        'EndDate'=>date('Y-m-d')
                    ], 'data');

                    //Если данные есть
                    //Суммироем их для кампаний
                    if(!$YandexApi->hasApiError()&&$stats){
                        //dump($stats);
                        foreach($stats as $stat){
                            if(!isset($total_stat[$stat['CampaignID']])){
                                $total_stat[$stat['CampaignID']]=[
                                    'sum'=>($stat['SumSearch']+$stat['SumContext']),
                                    'shows'=>($stat['ShowsSearch']+$stat['ShowsContext']),
                                    'clicks'=>($stat['ClicksSearch']+$stat['ClicksContext'])
                                ];
                            } else {
                                $total_stat[$stat['CampaignID']]['sum']+=($stat['SumSearch']+$stat['SumContext']);
                                $total_stat[$stat['CampaignID']]['shows']+=($stat['ShowsSearch']+$stat['ShowsContext']);
                                $total_stat[$stat['CampaignID']]['clicks']+=($stat['ClicksSearch']+$stat['ClicksContext']);
                            }
                        }
                    } else if($YandexApi->hasApiError()){
                        print_r($YandexApi->getError());
                    }
                }

                //проходим по суммарной статистике и по каждому показателю
                //Если для показателя в кампании есть лимит сравниваем если он превышен
                //Делаем то, что указано в лимите, если лимитов несколько то делаем все (сообщение отправляем единожды)
                if($total_stat){
                //dump($total_stat);

                    foreach($total_stat as $key=>$campain_total_stats){

                        //Если у нас есть такая кампания //и она еще не остановленна
                        if(isset($Campains[$key])/*&&$Campains[$key]->getStatusShow()==='Yes'*/){

                            $limit_message_data=['Campain'=>$Campains[$key], 'campain_stop'=>false, 'site_name'=>$this->container->getParameter('site_name')];
                            $send_limit_message=false;

                            //'DayBudgetEnabled'=>'No'
                            foreach($campain_total_stats as $k=>$campain_total_stat){
                                //Тут проверяем:
                                //Если есть у кампаниии лимит определенного типа
                                if(isset($Campains[$key]->getLimits()[$k])){
                                    $Limit=$Campains[$key]->getLimits()[$k];

                                    //и этот лтмит принажлежит текущей временной группе
                                    //и он еще не использовался
                                    if($group_time==$Limit->getTime()&&array_search($Limit->getId(), $used_limits)===false){

                                        //и его значение меньше текущей статистики этого типа и он больше 0
                                        if($Limit->getValue()>0&&$campain_total_stat>=$Limit->getValue()){

                                            //Сохраняем ид сработавшего лимита для того что бы сключить
                                            //повторное действие по нему в другой временной группе
                                            $used_limits[]=$Limit->getId();

                                            //Остановка
                                            if($Limit->getStop()
                                            &&!($Limit->getType()==='sum'&&$Campains[$key]->getDayBudgetEnabled()==='Yes')){

                                                $limit_message_data['campain_stop']=true;






                                                /*$YandexApi->sendRequest('StopCampaign', ['CampaignID'=>$Campains[$key]->getCampaignID()]);
                                                if(!$YandexApi->hasApiError()){
                                                    $Campains[$key]->setStatusShow('No');
                                                }*/


                                                $request_data=[
                                                    'SelectionCriteria'=>['Ids'=>[$Campains[$key]->getCampaignID()]]
                                                ];
                                                $YandexApi5->send5Request('campaigns', 'suspend', $request_data, [], 'SuspendResults');
                                                if(!$YandexApi5->hasApiError()){
                                                    $Campains[$key]->setStatusShow('No');
                                                }

                                                //Устанавливаем флаг авто старта
                                                if($Limit->getResume()){
                                                    $auto_start_limits[$Campains[$key]->getId()][]=$Limit->getId();
                                                    $Campains[$key]->setAutoStart(true);
                                                    $Campains[$key]->setAutoStartDate($campains_ids_group['auto_start_date']);
                                                }

                                            }

                                            if($Limit->getNote()){
                                                $send_limit_message=true;
                                            }

                                            $limit_message_data['Limits'][]=$Limit;

                                            $Logger->info('Для кампании: '.$Campains[$key]->getId().' достигнут установленный лимит', ['limit_id'=>$Limit->getId()]);

                                            //Если лимит не выполняется
                                            //и нет лимитов запускающих автостарт для кампании снимаем автостарт
                                            //на случай когда мы расширили лимит или отменили его, а автостарт уже был включен
                                            //Как вариант можно при редактировании лимита это делать -
                                            //снимать автостарт если лимит изменился, но вдумчиво
                                        } else if(!isset($auto_start_limits[$Campains[$key]->getId()])){
                                            $Campains[$key]->setAutoStart(false);
                                            $Campains[$key]->setAutoStartDate('0000-00-00');
                                        }
                                    }
                                }
                            }

                            //dump($limit_message_data);

                            //Если надо отправляем сообщение
                            if($send_limit_message){

                                $LimitMessage->setBody($this->renderView('BroAppBundle:Manage:Emails/campain_limit.html.twig', $limit_message_data), 'text/html')
                                    ->setTo($Campains[$key]->getYandexLogin()->getUser()->getEmail());
                                $this->get('bro.mailer')->setUser($Campains[$key]->getYandexLogin()->getUser())->send($LimitMessage);
                            }

                        }
                    }
                }

            }
        }

        //dump($used_limits);

        $EM->flush();
        $EM->clear();


        $end_time=(microtime(true) - $start);
        $end_memory=memory_get_usage();

        return $this->render('BroAppBundle:StaticElements:test.html.twig', array('time'=>$end_time, 'memory'=>$end_memory-$start_memory));
    }



    public function uploadedCampainImagesAction($CampaignID, $contaner=false){
        $Request=$this->getRequest();
        $EM=$this->getDoctrine()->getManager();
        $YandexApi = $this->get('yandex_api');

        $Campain=$EM->getRepository('BroAppBundle:Campain')->findOneBy(['CampaignID'=>$CampaignID]);
        $campain_pics=[];
        $pics_hashes=[];

        if($Campain){
            $YandexApi->setToken($Campain->getYandexLogin()->getToken());

            $pics=$YandexApi->sendRequest('AdImageAssociation', [
                'Action'=>'Get',
                'SelectionCriteria'=>[
                    'Logins'=>[$Campain->getYandexLogin()->getLogin()],
                    'CampaignID'=>[$Campain->getCampaignID()]
                ]
            ], 'data');

            if(!$YandexApi->hasApiError()&&count($pics['AdImageAssociations'])){
                foreach($pics['AdImageAssociations'] as $pic){
                    $pics_hashes[]=$pic['AdImageHash'];
                }

                $pics=$YandexApi->sendRequest('AdImage', [
                    'Action'=>'Get',
                    'SelectionCriteria'=>[
                        'Logins'=>[$Campain->getYandexLogin()->getLogin()],
                        'AdImageHashes'=>$pics_hashes
                    ]
                ], 'data');

                if(!$YandexApi->hasApiError()&&count($pics['AdImages'])){
                    $campain_pics=$pics['AdImages'];
                }

            }



            return $this->render('BroAppBundle:Manage/Campain:uploaded_campain_images.html.twig', ['campain_pics'=>$campain_pics]);
        }

        return new Response();
    }










    function createCampainParams($CampainForm, &$campaignParams, $campaignGoals, $timeZones){

        //Устанавливаем значения для передачи яндексу
        $campaignParams['Name']=$CampainForm['Name']->getData();
        $campaignParams['FIO']=$CampainForm['FIO']->getData();
        $campaignParams['StartDate']=$CampainForm['StartDate']->getData()->format('Y-m-d');

        $campaignParams['EmailNotification']['Email']=$CampainForm['EmailNotification']['Email']->getData();
        $campaignParams['EmailNotification']['WarnPlaceInterval']=$CampainForm['EmailNotification']['WarnPlaceInterval']->getData();
        $campaignParams['EmailNotification']['MoneyWarningValue']=$CampainForm['EmailNotification']['MoneyWarningValue']->getData();
        $campaignParams['EmailNotification']['SendAccNews']=$CampainForm['EmailNotification']['SendAccNews']->getData()?'Yes':'No';
        $campaignParams['EmailNotification']['SendWarn']=$CampainForm['EmailNotification']['SendWarn']->getData()?'Yes':'No';

        $campaignParams['SmsNotification']['MetricaSms']=$CampainForm['SmsNotification']['MetricaSms']->getData()?'Yes':'No';
        $campaignParams['SmsNotification']['ModerateResultSms']=$CampainForm['SmsNotification']['ModerateResultSms']->getData()?'Yes':'No';
        $campaignParams['SmsNotification']['MoneyInSms']=$CampainForm['SmsNotification']['MoneyInSms']->getData()?'Yes':'No';
        $campaignParams['SmsNotification']['MoneyOutSms']=$CampainForm['SmsNotification']['MoneyOutSms']->getData()?'Yes':'No';

        if(!$CampainForm['SmsNotification']['SmsTimeAll']->getData()){
            $campaignParams['SmsNotification']['SmsTimeFrom']=$CampainForm['SmsNotification']['SmsTimeFromHour']->getData().':'.$CampainForm['SmsNotification']['SmsTimeFromMinute']->getData();
            $campaignParams['SmsNotification']['SmsTimeTo']=$CampainForm['SmsNotification']['SmsTimeToHour']->getData().':'.$CampainForm['SmsNotification']['SmsTimeToMinute']->getData();
        } else {
            $campaignParams['SmsNotification']['SmsTimeFrom']='00:00';
            $campaignParams['SmsNotification']['SmsTimeTo']='24:00';
        }


        //Формируем массив параметров стратегии
        $campaignParams['Strategy']=[];

        if($CampainForm['Strategy']['SearchShowsDisabled']->getData()){

            switch ($CampainForm['Strategy']['StrategyName']->getData()){

                //Средняя цена клика
                case 'AverageClickPrice':
                    $campaignParams['Strategy']['StrategyName']='AverageClickPrice';
                    $campaignParams['Strategy']['AveragePrice']=$CampainForm['Strategy']['AveragePrice']->getData();
                    $campaignParams['Strategy']['WeeklySumLimit']=$CampainForm['Strategy']['AverageClickPriceWeeklySumLimit']->getData();
                break;

                //Средняя цена конверсии
                case 'AverageCPAOptimization':
                    //нужна валидация
                    $campaignParams['Strategy']['StrategyName']='AverageCPAOptimization';

                    //НАВЕРНО НЕПРАВИЛЬНО
                    //нужно учитывать доступные цели для поиска и РСЯ
                    if(count($campaignGoals)){
                        $campaignParams['Strategy']['AverageCPA']=$CampainForm['Strategy']['AverageCPA']->getData();
                        $campaignParams['Strategy']['GoalID']=$CampainForm['Strategy']['AverageCPAkGoalID']->getData();
                        $campaignParams['Strategy']['WeeklySumLimit']=$CampainForm['Strategy']['AverageCPAWeeklySumLimit']->getData();
                        $campaignParams['Strategy']['MaxPrice']=$CampainForm['Strategy']['AverageCPAMaxPrice']->getData();
                    }
                break;

                //Средняя цена конверсии
                case 'ROIOptimization':
                    $campaignParams['Strategy']['StrategyName']='ROIOptimization';

                    //НАВЕРНО НЕПРАВИЛЬНО
                    //нужно учитывать доступные цели для поиска и РСЯ
                    if(count($campaignGoals)){
                        $campaignParams['Strategy']['ReserveReturn']=$CampainForm['Strategy']['ReserveReturn']->getData();
                        $campaignParams['Strategy']['ROICoef']=$CampainForm['Strategy']['ROICoef']->getData();
                        $campaignParams['Strategy']['GoalID']=$CampainForm['Strategy']['ROIGoalID']->getData();
                        $campaignParams['Strategy']['Profitability']=$CampainForm['Strategy']['Profitability']->getData();

                        $campaignParams['Strategy']['WeeklySumLimit']=$CampainForm['Strategy']['ROIWeeklySumLimit']->getData();
                        $campaignParams['Strategy']['MaxPrice']=$CampainForm['Strategy']['ROIMaxPrice']->getData();
                    }
                break;


                //Недельный бюджет
                case 'WeeklyBudget':

                    if(!$CampainForm['Strategy']['CPAOptimizer']->getData()){
                        $campaignParams['Strategy']['StrategyName']='WeeklyBudget';
                    } else {
                        $campaignParams['Strategy']['StrategyName']='CPAOptimizer';
                        $campaignParams['Strategy']['GoalID']=$CampainForm['Strategy']['GoalID']->getData();
                    }

                    $campaignParams['Strategy']['WeeklySumLimit']=$CampainForm['Strategy']['WeeklySumLimit']->getData();
                    $campaignParams['Strategy']['MaxPrice']=$CampainForm['Strategy']['MaxPrice']->getData();

                break;

                //Показ в блоке по минимальной цене
                case 'LowestCost':
                    $campaignParams['Strategy']['StrategyName']='LowestCost';
                    if(!$CampainForm['Strategy']['LowestCostPremium']->getData()){
                        $campaignParams['Strategy']['StrategyName']='LowestCostPremium';
                    }
                break;

                //Показ под результатами поиска
                case 'LowestCostGuarantee':
                    $campaignParams['Strategy']['StrategyName']='LowestCostGuarantee';
                    if(!$CampainForm['Strategy']['LowestCostGuarantee']->getData()){
                        $campaignParams['Strategy']['StrategyName']='RightBlockHighest';
                    }
                break;

                //Недельный пакет кликов
                case 'WeeklyPacketOfClicks':
                    $campaignParams['Strategy']['StrategyName']='WeeklyPacketOfClicks';
                    $campaignParams['Strategy']['ClicksPerWeek']=$CampainForm['Strategy']['ClicksPerWeek']->getData();
                    $campaignParams['Strategy'][$CampainForm['Strategy']['ClicksPerWeekType']->getData()]=$CampainForm['Strategy']['ClicksPerWeekPrice']->getData();
                break;

                default:
                    $campaignParams['Strategy']['StrategyName']=$CampainForm['Strategy']['StrategyName']->getData();
                break;
            }
        } else {
            $campaignParams['Strategy']['StrategyName']='ShowsDisabled';
        }


        $campaignParams['StatusBehavior']=$CampainForm['Strategy']['StatusBehavior']->getData()?'Yes':'No';

        //Формируем массив параметров стратегии на РСЯ
        $campaignParams['ContextStrategy']=[];
        if($CampainForm['Strategy']['ContextShowsDisabled']->getData()){


            //Че за нах
            //раньше была такая херня
            //$Request->request->get('Campain')['Strategy']['ContextStrategyName']
            //Возможно из-за дизейбленных селектов
            //Но сейчас вроде робит
            switch($CampainForm['Strategy']['ContextStrategyName']->getData()){

                case 'Default':
                    $campaignParams['ContextStrategy']['StrategyName']='Default';
                    $campaignParams['ContextStrategy']['ContextLimit']='Limited';
                    $campaignParams['ContextStrategy']['ContextLimitSum']=$CampainForm['Strategy']['ContextLimitSum']->getData();
                    $campaignParams['ContextStrategy']['ContextPricePercent']=$CampainForm['Strategy']['ContextPricePercent']->getData();
                break;

                //Средняя цена клика
                case 'AverageClickPrice':
                    $campaignParams['ContextStrategy']['StrategyName']='AverageClickPrice';
                    $campaignParams['ContextStrategy']['AveragePrice']=$CampainForm['Strategy']['ContextAveragePrice']->getData();
                    $campaignParams['ContextStrategy']['WeeklySumLimit']=$CampainForm['Strategy']['ContextAverageClickPriceWeeklySumLimit']->getData();
                break;

                //Средняя цена конверсии
                case 'AverageCPAOptimization':
                    //нужна валидация
                    $campaignParams['ContextStrategy']['StrategyName']='AverageCPAOptimization';

                    //НАВЕРНО НЕПРАВИЛЬНО
                    //нужно учитывать доступные цели для поиска и РСЯ
                    if(count($campaignGoals)){
                        $campaignParams['ContextStrategy']['AverageCPA']=$CampainForm['Strategy']['ContextAverageCPA']->getData();
                        $campaignParams['ContextStrategy']['GoalID']=$CampainForm['Strategy']['ContextAverageCPAGoalID']->getData();
                        $campaignParams['ContextStrategy']['WeeklySumLimit']=$CampainForm['Strategy']['ContextAverageCPAWeeklySumLimit']->getData();
                        $campaignParams['ContextStrategy']['MaxPrice']=$CampainForm['Strategy']['ContextAverageCPAMaxPrice']->getData();
                    }
                break;

                //Средняя цена конверсии
                case 'ROIOptimization':
                    $campaignParams['ContextStrategy']['StrategyName']='ROIOptimization';

                    //НАВЕРНО НЕПРАВИЛЬНО
                    //нужно учитывать доступные цели для поиска и РСЯ
                    if(count($campaignGoals)){
                        $campaignParams['ContextStrategy']['ReserveReturn']=$CampainForm['Strategy']['ContextReserveReturn']->getData();
                        $campaignParams['ContextStrategy']['ROICoef']=$CampainForm['Strategy']['ContextROICoef']->getData();
                        $campaignParams['ContextStrategy']['GoalID']=$CampainForm['Strategy']['ContextROIGoalID']->getData();
                        $campaignParams['ContextStrategy']['Profitability']=$CampainForm['Strategy']['ContextProfitability']->getData();

                        $campaignParams['ContextStrategy']['WeeklySumLimit']=$CampainForm['Strategy']['ContextROIWeeklySumLimit']->getData();
                        $campaignParams['ContextStrategy']['MaxPrice']=$CampainForm['Strategy']['ContextROIMaxPrice']->getData();
                    }
                break;

                //Недельный бюджет
                case 'WeeklyBudget':

                    if(!$CampainForm['Strategy']['ContextCPAOptimizer']->getData()){
                        $campaignParams['ContextStrategy']['StrategyName']='WeeklyBudget';
                    } else {
                        $campaignParams['ContextStrategy']['StrategyName']='CPAOptimizer';
                        $campaignParams['ContextStrategy']['GoalID']=$CampainForm['Strategy']['ContextGoalID']->getData();
                    }

                    $campaignParams['ContextStrategy']['WeeklySumLimit']=$CampainForm['Strategy']['ContextWeeklySumLimit']->getData();
                    $campaignParams['ContextStrategy']['MaxPrice']=$CampainForm['Strategy']['ContextMaxPrice']->getData();

                break;

                //Недельный пакет кликов
                case 'WeeklyPacketOfClicks':
                    $campaignParams['ContextStrategy']['StrategyName']='WeeklyPacketOfClicks';
                    $campaignParams['ContextStrategy']['ClicksPerWeek']=$CampainForm['Strategy']['ContextClicksPerWeek']->getData();
                    $campaignParams['ContextStrategy'][$CampainForm['Strategy']['ContextClicksPerWeekType']->getData()]=$CampainForm['Strategy']['ContextClicksPerWeekPrice']->getData();
                break;

                default:
                    $campaignParams['ContextStrategy']['StrategyName']=$CampainForm['Strategy']['ContextStrategyName']->getData();
                break;
            }
        } else {
            $campaignParams['ContextStrategy']['StrategyName']='ShowsDisabled';
        }


        if($campaignParams['DayBudgetEnabled']=='Yes'){
            $campaignParams['DailyBudget']['Amount']=$CampainForm['DayBudgetAmount']->getData();
            $campaignParams['DailyBudget']['SpendMode']=$CampainForm['DayBudgetSpendMode']->getData();
        }

        $campaignParams['TimeTarget']=[];
        //Формируем массив временных параметров
        $campaignParams['TimeTarget']['ShowOnHolidays']=$CampainForm['TimeTarget']['ShowOnHolidays']->getData()?'Yes':'No';
        if($campaignParams['TimeTarget']['ShowOnHolidays']=='Yes'){
            $campaignParams['TimeTarget']['HolidayShowFrom']=$CampainForm['TimeTarget']['HolidayShowFrom']->getData();
            $campaignParams['TimeTarget']['HolidayShowTo']=$CampainForm['TimeTarget']['HolidayShowTo']->getData();
        }

        $campaignParams['TimeTarget']['DaysHours']=json_decode($CampainForm['TimeTarget']['DaysHours']->getData(), true);
        $campaignParams['TimeTarget']['TimeZone']=$timeZones[$CampainForm['TimeTarget']['TimeZone']->getData()]['TimeZone'];
        $campaignParams['TimeTarget']['WorkingHolidays']=$CampainForm['TimeTarget']['WorkingHolidays']->getData()?'Yes':'No';


        $campaignParams['MinusKeywords']=explode(' ', str_replace('-', '', $CampainForm['MinusKeywords']->getData()));
        $campaignParams['AddRelevantPhrases']=$CampainForm['AddRelevantPhrases']->getData()?'Yes':'No';
        $campaignParams['RelevantPhrasesBudgetLimit']=$CampainForm['RelevantPhrasesBudgetLimit']->getData();
        $campaignParams['StatusMetricaControl']=$CampainForm['StatusMetricaControl']->getData()?'Yes':'No';

        $campaignParams['StatusMetricaControl']=$CampainForm['StatusMetricaControl']->getData()?'Yes':'No';

        $metrikaCounters=[];
        if($CampainForm['AdditionalMetrikaCounters']->getData()){
            $metrikaCounters_list=$dotCounters=trim(str_replace('  ', ' ', str_replace(',', ' ', $CampainForm['AdditionalMetrikaCounters']->getData())));
            $metrikaCounters=explode(' ', $metrikaCounters_list);
            //dump($metrikaCounters);
        }
        $campaignParams['AdditionalMetrikaCounters']=$metrikaCounters;
        $campaignParams['ClickTrackingEnabled']=$CampainForm['ClickTrackingEnabled']->getData()?'Yes':'No';
        $campaignParams['MobileBidAdjustment']=$CampainForm['MobileBidAdjustment']->getData();

        $campaignParams['DisabledDomains']=trim(str_replace(' ', ',', str_replace(', ', ',', $CampainForm['DisabledDomains']->getData())));
        $campaignParams['DisabledIps']=trim(str_replace(' ', ',', str_replace(', ', ',', $CampainForm['DisabledIps']->getData())));
        $campaignParams['EnableRelatedKeywords']=$CampainForm['EnableRelatedKeywords']->getData()?'Yes':'No';
        $campaignParams['ExtendedAdTitleEnabled']=$CampainForm['ExtendedAdTitleEnabled']->getData()?'Yes':'No';
        $campaignParams['AutoOptimization']=$CampainForm['AutoOptimization']->getData()?'Yes':'No';
        $campaignParams['ConsiderTimeTarget']=$CampainForm['ConsiderTimeTarget']->getData()?'Yes':'No';
        $campaignParams['StatusOpenStat']=$CampainForm['StatusOpenStat']->getData()?'Yes':'No';


        return true;

    }








    //**************************************************
    //****************** API ДЕЙСТВИЯ ******************
    //**************************************************
    public function controllCampainAction($campain_id, $action,  $filter=false, $banner_id=false, $render_response=true){
        $Request=$this->getRequest();
        $YandexApi = $this->get('yandex_api5');
        $EM=$this->getDoctrine()->getManager();

        if($Request->request->has('render_response')){
            $render_response=$Request->request->get('render_response');
        }

        $Campains=$EM->getRepository('BroAppBundle:Campain')->findFullById($campain_id, $filter, $banner_id);
        if(count($Campains)>0){
            $request_data=array();

            foreach($Campains as $key=>&$Campain){

                if($EM->getRepository('BroAppBundle:Campain')->checkUserAcces($Campain->getId(), $this->getUser())){

                    if($key==0){
                        $YandexApi->setToken5($Campain->getYandexLogin()->getToken(), $Campain->getYandexLogin());
                    }

                    $request_data['CampaignID']=$Campain->getCampaignID();


                    //Остановка кампании
                    $request_data=[
                        'SelectionCriteria'=>['Ids'=>[$Campain->getCampaignID()]]
                    ];

                    //if($action=='StopCampaign'&&$YandexApi->send5Request('StopCampaign', $request_data)){
                    if($action=='StopCampaign'&&$YandexApi->send5Request('campaigns', 'suspend', $request_data, [], 'SuspendResults')){
                        $Campain->setStatusShow('No');
                    }

                    //Возобнавление кампании
                    if($action=='ResumeCampaign'){
                        //if($Campain->getStatusArchive()=='Yes'&&$YandexApi->sendRequest('UnArchiveCampaign', $request_data)){
                        if($Campain->getStatusArchive()=='Yes'&&$YandexApi->send5Request('campaigns', 'unarchive', $request_data, [], 'UnarchiveResults')){

                            $Campain->setStatusArchive('No');
                        }

                        //if(!$YandexApi->hasApiError()&&$YandexApi->sendRequest('ResumeCampaign', $request_data)){
                        if(!$YandexApi->hasApiError()&&$YandexApi->send5Request('campaigns', 'resume', $request_data, [], 'ResumeResults')){

                            $Campain->setStatusShow('Yes');
                        }
                    }

                    //Архивирование
                    if($action=='ArchiveCampaign'){
                        if($Campain->GetRest()==0){

                            //if($Campain->getStatusShow()=='Yes'&&$YandexApi->sendRequest('StopCampaign', $request_data)){
                            if($Campain->getStatusShow()=='Yes'&&$YandexApi->send5Request('campaigns', 'suspend', $request_data, [], 'SuspendResults')){

                                $Campain->setStatusShow('No');
                            }

                            //if(!$YandexApi->hasApiError()&&$YandexApi->sendRequest('ArchiveCampaign', $request_data)){
                            if(!$YandexApi->hasApiError()&&$YandexApi->send5Request('campaigns', 'archive', $request_data, [], 'ArchiveResults')){
                                $Campain->setStatusArchive('Yes');
                            }

                        } else {
                            //TODO:
                            //Если общий счет у рекламодателя не подключен, поместить в архив можно только кампанию с нулевым балансом. Если у рекламодателя подключен общий счет, поместить в архив можно любую кампанию.
                            $this->AjaxResponse->addError('cc_001', 'Для архивирования доступны только кампании с нулевым балансом');
                        }

                    }

                    //Разархивирование
                    //if($action=='UnArchiveCampaign'&&$YandexApi->sendRequest('UnArchiveCampaign', $request_data)){
                    if($action=='UnArchiveCampaign'&&$YandexApi->send5Request('campaigns', 'unarchive', $request_data, [], 'UnarchiveResults')){

                        $Campain->setStatusArchive('No');
                    }



                    if($YandexApi->hasApiError()){
                        $this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
                    } else {
                        $EM->flush();
                    }

                    if(!$this->AjaxResponse->getHasErrors()){
                        if($render_response){
                            $this->AjaxResponse->setData(array('workflow'=>$this->forwardBack()->getContent()), 'html', 'ok');
                        } else {
                            $this->AjaxResponse->setStatus('ok');
                        }
                    }

                } else {
                    $this->AjaxResponse->addError('s_002', 'У вас недостаточно прав для этого действия');
                    break;
                }
            }
            unset($Campain);

        }

        return $this->AjaxResponse->getResponse();
    }


}
