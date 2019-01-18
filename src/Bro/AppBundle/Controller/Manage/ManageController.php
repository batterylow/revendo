<?php

namespace Bro\AppBundle\Controller\Manage;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;

use Bro\AppBundle\Entity\AjaxResponse;

use Bro\AppBundle\Entity\User;
use Bro\AppBundle\Entity\YandexLogin;
use Bro\AppBundle\Entity\Strategy;
use Bro\AppBundle\Entity\Campain;
use Bro\AppBundle\Entity\AdGroup;
use Bro\AppBundle\Entity\Banner;
use Bro\AppBundle\Entity\Phrase;
use Bro\AppBundle\Entity\UserFlag;


use Bro\AppBundle\Controller\Manage\PhrasesController;
use Bro\AppBundle\Controller\Manage\BannersController;
use Bro\AppBundle\Controller\Manage\CampainsController;

use Bro\AppBundle\Controller\StaticElementsController;
use Bro\ApiBundle\Controller\YandexApiController;

class ManageController extends Controller {

    use \Bro\AppBundle\Traits\AjaxController;

    private $AjaxResponse;

    function __construct(){
        $this->AjaxResponse=AjaxResponse::getInstance();
    }


    //Выводим логины пользователя с краткой инфой
    public function manageAction($filter='active', $YandexLogin=false) {
        $Request=$this->getRequest();
        $EM = $this->getDoctrine()->getManager();
        $User = $this->getUser();



        /*          $Message=$this->get('bro.mailer')->getService()
        ->createMessage()
        ->setSubject('Добро пожаловать!')
        ->setFrom($this->container->getParameter('sender_email'))
        ->setTo('check-auth2@verifier.port25.com')
        ->setBody($this->renderView('BroAppBundle:Security:Email/registration.html.twig'), 'text/html');
        $this->get('bro.mailer')->send($Message);
        web-hZaFu7@mail-tester.com
        */
        //dump(md5(uniqid()));


        /*    $YandexApi=$this->get('yandex_api');
        $YandexLogin=$EM->getRepository('BroAppBundle:YandexLogin')->findOneByLogin('distorsia');
        $YandexApi->setToken($YandexLogin->getToken());
        //$result=$YandexApi->sendRequest('GetChanges', ['CampaignIDS'=>['5751898'], 'Timestamp'=>'2015-04-24T15:46:58Z'], 'data');
        //$result=$YandexApi->sendRequest('GetChanges', ['Logins'=>['distorsia'], 'Timestamp'=>'2015-04-24T15:08:13Z'], 'data');
        //$result=$YandexApi->sendRequest('GetChanges', ['CampaignIDS'=>['12580600'], 'Timestamp'=>'2015-04-24T16:11:02Z'], 'data');
        //$result=$YandexApi->sendRequest('GetBanners', ['CampaignIDS'=>['12661743'], 'GetPhrases'=>'Yes'], 'data');

        if(!$YandexApi->hasApiError()){
        dump($result);
        } else {
        dump($YandexApi->getError());
        }
        */



        //Если есть новые логины загружаем их
        $NewYandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findUserNewYandexLogins($User->getId());
        if($NewYandexLogins){
            $yandex_logins_data=$EM->getRepository('BroAppBundle:YandexLogin')->findUserNewYandexLoginsCount($User->getId());

            //Получаем актуальную инфу о процессе загрузки
            $load_data=['yandex_logins'=>[
                    'all'=>$yandex_logins_data['n_yandex_logins'],
                    'active'=>$yandex_logins_data['n_active_yandex_logins']
                ],
                'campains'=>['all'=>$yandex_logins_data['n_campains'], 'active'=>$yandex_logins_data['n_active_campains']],
                'progress'=>0
            ];

            if($yandex_logins_data['n_active_campains']>0){
                $load_data['progress']=round($yandex_logins_data['n_active_campains']*100/$yandex_logins_data['n_campains'], 0);
            }

            if($Request->isXmlHttpRequest()){
                $this->AjaxResponse->setData($load_data, 'json', 'ok');
                return $this->AjaxResponse->getResponse();
            }
            return $this->render('BroAppBundle:Manage:/errors/loadNewLoginsData.html.twig', $load_data);
            //return $this->loadNewLoginsDataAction();
        }


        $YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findUserRootYandexLoginsWithCampains($User->getId(), 'active');


        $yandex_logins_list=array();
        $role='all';


        if($YandexLogins){

            //Если всего один логин и это клиент
            //редиертим сразу на страницу логина
            if(count($YandexLogins)==1&&!$Request->isXmlHttpRequest()){
                reset($YandexLogins);
                $one_login=key($YandexLogins);
                if($YandexLogins[$one_login]->getRole()=='Client'){
                    return $this->redirect($this->generateUrl('manage_yandex_login', array('yandex_login'=>$YandexLogins[$one_login]->getLogin())));
                }
            }

            //Если передан логин берем его клиентов
            if($YandexLogin){
                $ManageYandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findByParentLogin($YandexLogin, $filter);
                //dump($ManageYandexLogins);
                $role='clients';
            } else {
                $ManageYandexLogins=$YandexLogins;
            }

            //сохраняем список логинов для последующей выборки краткой инфы
            //И получение статистики для них
            $start_date=date('Y-m-d',strtotime('now - 6 days'));
            $end_date=date('Y-m-d',strtotime('now'));
            foreach($ManageYandexLogins as $ManageYandexLogin){
                $yandex_logins_list[]=$ManageYandexLogin->GetLogin();
                //TODO: Вынести из цикла и получить данные 1 запросом для всех логинов
                $Stats[$ManageYandexLogin->GetLogin()]=$EM->getRepository('BroAppBundle:Stat')->findSummForDateByYandexLogin($ManageYandexLogin->GetLogin(), $start_date, $end_date);
            }


            $YandexLoginsResume=$EM->getRepository('BroAppBundle:YandexLogin')->findLoginsResume($yandex_logins_list, $role);


            $workflow=[
                'standalone'=>$Request->attributes->get('standalone'),
                'only_content'=>$Request->attributes->get('only_content'),
                'User'=>$User,
                'YandexLogins'=>$YandexLogins,
                'filter'=>$filter,
                'YandexLoginsResume'=>$YandexLoginsResume,
                'ManageYandexLogins'=>$ManageYandexLogins,
                'Stats'=>$Stats,
                'yandex_login'=>is_object($YandexLogin)?$YandexLogin->getLogin():false,
                'yandex_client'=>false,
                'campain_id'=>false
            ];

            if($Request->getMethod()=='POST'&&$Request->isXmlHttpRequest()){

                $workflow['standalone']=true;

                $this->AjaxResponse->setData(array('workflow'=>$this->render('BroAppBundle:Manage:manage.html.twig', $workflow)->getContent()), 'html', 'ok');
                return $this->AjaxResponse->getResponse();

            } else {
                return $this->render('BroAppBundle:Manage:manage.html.twig', $workflow);
            }


        } else {
            return $this->render('BroAppBundle:Manage:errors/no_yandex_logins.html.twig');
        }
    }

    //Выводим логин
    public function yandexLoginAction($filter='active', $yandex_login, $yandex_parent_login=false, $campains_filter='active', $campain_id=false) {
        $Request=$this->getRequest();
        $EM = $this->getDoctrine()->getManager();
        $User = $this->getUser();

        $YandexLogin=$EM->getRepository('BroAppBundle:YandexLogin')->findFullOneByLogin($yandex_login, true, $campains_filter, $campain_id);
        if($YandexLogin->getRole()=='Agency'){
            return $this->forward('BroAppBundle:Manage/Manage:manage', [
                    'standalone'=>$Request->attributes->get('standalone'),
                    'only_content'=>$Request->attributes->get('only_content'),
                    'YandexLogin'=>$YandexLogin,
                    'filter'=>$filter
                ]
            );
        }

        if($YandexLogin->getStatus()!=='transfer'){
            $EM->detach($YandexLogin);
            $YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findUserRootYandexLoginsWithCampains($User->getId(), ['filter'=>'active', 'logins'=>[$yandex_login]]);

            $workflow=[
                'standalone'=>$Request->attributes->get('standalone'),
                'only_content'=>$Request->attributes->get('only_content'),
                'User'=>$User,
                'YandexLogins'=>$YandexLogins,
                //'yandex_login'=>$YandexLogins[$yandex_login], - хмммм
                'yandex_login'=>$yandex_parent_login?$yandex_parent_login:$yandex_login,
                'yandex_client'=>$yandex_parent_login?$yandex_login:false,
                'campains_filter'=>$campains_filter,
                'campain_id'=>$campain_id
            ];

            if($YandexLogins){
                $workflow['Strategys']=$EM->getRepository('BroAppBundle:Strategy')->findUserStrategys($User->getId());
                $template='BroAppBundle:Manage:yandex_login.html.twig';

                //Иначе доктрина берет данные из выборки логинов для сайдбара
                if(isset($YandexLogins[$yandex_login])){
                    $EM->detach($YandexLogins[$yandex_login]);
                }

                if($YandexLogin){
                    $workflow['Campains']=$YandexLogin->getCampains();
                }

                //return $this->smartRender($template, $workflow, $Request);
                return $this->render($template, $workflow);

            } else {
                return $this->render('BroAppBundle:Manage:errors/no_yandex_logins.html.twig');
            }

        } else {
            return $this->render('BroAppBundle:Manage:errors/global_yandex_login_error.html.twig',[
                    'error_header'=>'Происходит переход на бесплатный тариф',
                    'error_content'=>'В это время управление кампаниями не доступно, после окончания процесса вам придёт уведомление на почту.'
                ]
            );
        }

    }

    //Выводим клиента
    public function yandexClientAction($yandex_login=false, $yandex_client=false, $campains_filter='active') {
        $Request=$this->getRequest();
        $EM = $this->getDoctrine()->getManager();
        $User = $this->getUser();

        $YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findUserRootYandexLoginsWithCampains($User->getId());

        $workflow=[
            'standalone'=>$Request->attributes->get('standalone'),
            'only_content'=>$Request->attributes->get('only_content'),
            'User'=>$User,
            'YandexLogins'=>$YandexLogins,
            'yandex_login'=>$yandex_login,
            'yandex_client'=>$yandex_client,
            'campains_filter'=>$campains_filter,
            'campain_id'=>false
        ];

        if($YandexLogins){
            $workflow['Strategys']=$EM->getRepository('BroAppBundle:Strategy')->findUserStrategys($User->getId());

            $YandexLogin=$EM->getRepository('BroAppBundle:YandexLogin')->findFullOneByLogin($yandex_client, true, $campains_filter);
            $workflow['Campains']=$YandexLogin->getCampains();

            //return $this->smartRender('BroAppBundle:Manage:yandex_login.html.twig', $workflow, $Request);
            return $this->render('BroAppBundle:Manage:yandex_login.html.twig', $workflow);

        } else {
            return $this->render('BroAppBundle:Manage:errors/no_yandex_logins.html.twig');
        }
    }

    //Выводим кампанию
    public function campainAction($filter='active', $yandex_login=false, $yandex_client=false, $campain_id=false, $ad_group_id=false) {
        $Request=$this->getRequest();
        $EM = $this->getDoctrine()->getManager();
        $User = $this->getUser();

        $YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findUserRootYandexLoginsWithCampains($User->getId());

        $workflow=[
            'standalone'=>$Request->attributes->get('standalone'),
            'only_content'=>$Request->attributes->get('only_content'),
            'User'=>$User,
            'YandexLogins'=>$YandexLogins,
            'yandex_login'=>$yandex_login,
            'yandex_client'=>$yandex_client,
            'filter'=>$filter,
            'ad_group_id'=>$ad_group_id,
            'campain_id'=>$campain_id
        ];



        if($YandexLogins){

            if($campain_id){
                $workflow['Strategys']=$EM->getRepository('BroAppBundle:Strategy')->findUserStrategys($User->getId());
                $workflow['Campain']=$EM->getRepository('BroAppBundle:Campain')->findFullOneById($campain_id, $filter, $ad_group_id);
                //dump($workflow['Campain']);
            }

            //return $this->smartRender('BroAppBundle:Manage:campain.html.twig', $workflow, $Request);
            return $this->render('BroAppBundle:Manage:campain.html.twig', $workflow);

        } else {
            return $this->render('BroAppBundle:Manage:errors/no_yandex_logins.html.twig');
        }
    }















    //Загрузка кампаний новых логинов
    //И их клиентов соответственно тоже с кампаниями
    function loadNewLoginDataAction($yandex_login){

        $Request=$this->getRequest();
        $EM = $this->getDoctrine()->getManager();
        $YandexApi = $this->get('yandex_api');
        $YandexApi5 = $this->get('yandex_api5');

        $start = microtime(true);
        $start_memory=memory_get_usage();
        $end_memory=$start_memory;

        set_time_limit(0);

        if(!is_object($yandex_login)){
            $YandexLogin=$EM->getRepository('BroAppBundle:YandexLogin')->findOneByLogin($yandex_login);
        } else {
            $YandexLogin=$yandex_login;
        }
        $YandexApi->setToken($YandexLogin->getToken());
        $YandexApi5->setToken5($YandexLogin->getToken(), $YandexLogin);
        $StdStartegy=$EM->getRepository('BroAppBundle:Strategy')->findFirstOne();

        //Если просто логин
        //загружием кампании

        //Возможно загрузку кампаний стоит внести в отдельный скрипт запускаемый по крону
        //что бы не было долгого ожидания редиректа
        //для кампаний <=300 ожидание приемлимое, а дальше уже плоховато
        if($YandexLogin->getRole()=='Client'){

            //Загружаем кампании
            //Модно вынести в отдельную функцию, но сейчас лень
            //используется еще чуть ниже и в обновлении данных логинов, может еще где

            //$campains_list=$YandexApi->sendRequest('GetCampaignsList', [$YandexLogin->getLogin()], 'data');

            $request_data=['SelectionCriteria'=>['Types'=>['TEXT_CAMPAIGN']]];
            $request_data=array_merge($request_data, $EM->getRepository('BroAppBundle:Campain')->getStdYandexApiParams());

            $campains_list=$YandexApi5->send5Request('campaigns', 'get', $request_data, [], 'Campaigns');
            //dump($campains_list);

            if(!$YandexApi5->hasApiError()&&is_array($campains_list)){

                //Заполняем кампании
                foreach($campains_list as $campain){
                    $Campain=new Campain();


                    $Campain->fill($campain)
                        ->SetUser($YandexLogin->getUser())
                        ->SetYandexLogin($YandexLogin)
                        ->setDataStatus('new')
                        ->SetStrategy($StdStartegy)
                        ->setMaxPrice(0)
                        ->SetStartDate(new \DateTime($campain['StartDate']));

                    //dump($Campain);

                    $YandexLogin->addCampain($Campain);
                }

            }


        //Если агенство
        //загружаем клиентов с кампаниями
        } else if($YandexLogin->getRole()=='Agency'){

            $clients_list=$YandexApi->sendRequest('GetClientsList', [], 'data');
            if(!$YandexApi->hasApiError()&&is_array($clients_list)){

                $clients_logins=[];

                foreach($clients_list as $client){

                    $SubYandexLogin=$EM->getRepository('BroAppBundle:YandexLogin')->findOneBy(['login'=>$client['Login']]);

                    if(!$SubYandexLogin){

                        $SubYandexLogin=new YandexLogin();

                        $SubYandexLogin->fill($client)
                            ->setBullets($client)
                            ->setLogin($client['Login'])
                            ->setStatus('new')
                            ->SetDateCreate(new \DateTime($client['DateCreate']))
                            ->setParentLogin($YandexLogin)
                            ->setUser($YandexLogin->getUser())
                            ->setAccess(1);

                        $clients_logins[]=$SubYandexLogin->getLogin();

                        $YandexLogin->addSubLogin($SubYandexLogin);


                        $YandexApi5->setToken5($YandexLogin->getToken(), $SubYandexLogin);

                        $request_data=['SelectionCriteria'=>['Types'=>['TEXT_CAMPAIGN']]];
                        $request_data=array_merge($request_data, $EM->getRepository('BroAppBundle:Campain')->getStdYandexApiParams());

                        $campains_list=$YandexApi5->send5Request('campaigns', 'get', $request_data, [], 'Campaigns');
                        if(!$YandexApi5->hasApiError()&&is_array($campains_list)){

                            //Заполняем кампании
                            foreach($campains_list as $campain){
                                //dump($campain);
                                $Campain=new Campain();
                                $Campain->fill($campain)
                                    ->SetUser($SubYandexLogin->getUser())
                                    ->SetYandexLogin($SubYandexLogin)
                                    ->setDataStatus('new')
                                    ->SetStrategy($StdStartegy)
                                    ->setMaxPrice(0)
                                    ->SetStartDate(new \DateTime($campain['StartDate']));

                                $SubYandexLogin->addCampain($Campain);
                            }
                        }
                    }



                }

            }


        }

        //Сохраняем данные
        $EM->flush();

        $end_time=(microtime(true) - $start);
        $end_memory=memory_get_usage();

        return $this->redirectToRoute('manage');


        return $this->render('BroAppBundle:StaticElements:test.html.twig', array('time'=>$end_time, 'memory'=>$end_memory-$start_memory));

    }


    //Загрузка объявлений и фраз новых кампаний
    function loadNewCampainsDataAction($yandex_login=false, $step=1){
        $Request=$this->getRequest();
        $EM = $this->getDoctrine()->getManager();
        $YandexApi = $this->get('yandex_api');
        $YandexApi5 = $this->get('yandex_api5');
        $Logger = $this->get('monolog.logger.bro');

        $start = microtime(true);
        $start_memory=memory_get_usage();
        $end_memory=$start_memory;

        set_time_limit(0);

        //Количество компаний можно варьировать в зависимости от памяти сервера
        //Количество шагов тоже
        $n_campains=$this->container->getParameter('load_new_campains_n_items');
        $max_steps=$this->container->getParameter('load_new_campains_n_steps');


        if($yandex_login){
            $NewYandexLogin=$EM->getRepository('BroAppBundle:YandexLogin')->findOneByLogin($yandex_login);
        } else {
            //$NewYandexLogin=$EM->getRepository('BroAppBundle:YandexLogin')->findOneByStatus('new');
            $NewYandexLogin=$EM->getRepository('BroAppBundle:YandexLogin')->findOneNew();
        }


        if($NewYandexLogin){
            $YandexApi->setToken($NewYandexLogin->getToken());
            $YandexApi5->setToken5($NewYandexLogin->getToken(), $NewYandexLogin);

            //$NewCampains=$EM->getRepository('BroAppBundle:Campain')->findBy(['dataStatus'=>'new', 'YandexLogin'=>$NewYandexLogin], ['YandexLogin'=>'ASC'], $n_campains);
            $NewCampains=$EM->getRepository('BroAppBundle:Campain')->findNewByYandexLoginId($NewYandexLogin->getId(), $n_campains);

            if($NewCampains){

                $campains_id=[];

                //Помечаем что объявления загружаются
                //так как процесс долгий и что бы не было наложений
                foreach($NewCampains as $NewCampain){
                    $NewCampain->setDataStatus('upload');
                    $campains_id[]=$NewCampain->getCampaignID();
                }
                $EM->flush();


                //Загружмем данные
                //$banners_list=$YandexApi->sendRequest('GetBanners', $request_data, 'data');
                //$banners_lists=$YandexApi->sendLimitedRequest('GetBanners', ['CampaignIDS'=>$campains_id, 'GetPhrases'=>'WithPrices'], 'data');

                /*$request_data=['SelectionCriteria'=>['AdGroupIds'=>[$AdGroup->getAdGroupID()]]];
                $request_data=array_merge($request_data, $EM->getRepository('BroAppBundle:Banner')->getStdYandexApiParams());

                $banners=$YandexApi->send5Request('AdGroup', 'get', $request_data, [], 'Ads');*/


                //ЗАГРУЖАЕМ ГРУППЫ
                $AdGroups=[];
                $request_data=['SelectionCriteria'=>[
                    'CampaignIds'=>$campains_id,
                    'Types'=>['TEXT_AD_GROUP'],
                    ]
                ];
                $request_data=array_merge($request_data, $EM->getRepository('BroAppBundle:AdGroup')->getStdYandexApiParams());
                $adGroupsPacks=$YandexApi5->send5LimitedRequest('AdGroups', 'get', $request_data, [], 'AdGroups');

                //Идем по пакам групп
                if (!$YandexApi5->hasApiError() && !$adGroupsPacks['errors'] && count($adGroupsPacks['data'])) {
                    foreach ($adGroupsPacks['data'] as $adGroupsPack) {
                        if (count($adGroupsPack['result'])) {
                            foreach ($adGroupsPack['result'] as $adGroup) {

                                $AdGroup = new AdGroup();

                                $AdGroup->fill($adGroup)
                                    ->SetUser($NewCampains[$adGroup['CampaignId']]->getUser())
                                    ->SetYandexLogin($NewCampains[$adGroup['CampaignId']]->getYandexLogin())
                                    ->SetCampain($NewCampains[$adGroup['CampaignId']])
                                    ->SetStrategy($NewCampains[$adGroup['CampaignId']]->getStrategy())
                                    ->setMaxPrice($NewCampains[$adGroup['CampaignId']]->getMaxPrice());

                                $AdGroups[$adGroup['Id']]=$AdGroup;
                                /*$newAdGroupsIDs[]=$changedAdGroup['Id'];*/
                                $EM->persist($AdGroup);

                            }
                        }
                    }
                }


                //ЗАГРУЖАЕМ ОБЪЯВЛЕНИЯ
                $request_data=['SelectionCriteria'=>[
                    'CampaignIds'=>$campains_id,
                ]];
                $request_data=array_merge($request_data, $EM->getRepository('BroAppBundle:Banner')->getStdYandexApiParams());
                $bannersPacks=$YandexApi5->send5LimitedRequest('Ads', 'get', $request_data, [], 'Ads');

                //Идем по пакам объявлений
                if (!$YandexApi5->hasApiError() && !$bannersPacks['errors'] && count($bannersPacks['data'])) {
                    foreach ($bannersPacks['data'] as $bannersPack) {
                        if (count($bannersPack['result'])) {
                            foreach ($bannersPack['result'] as $banner) {

                                    $Banner = new Banner();

                                    $Banner->fill($banner)
                                        ->SetUser($AdGroups[$banner['AdGroupId']]->getUser())
                                        ->SetYandexLogin($AdGroups[$banner['AdGroupId']]->getYandexLogin())
                                        ->SetCampain($AdGroups[$banner['AdGroupId']]->getCampain())
                                        ->SetAdGroup($AdGroups[$banner['AdGroupId']]);

                                    //Он же не используется дальше, в отличие от группы, нахнагружать
                                    $EM->persist($Banner);
                            }
                        }
                    }
                }

                //ЗАГРУЖАЕМ ФРАЗЫ
                $Phrases=[];
                $request_data=['SelectionCriteria'=>['CampaignIds'=>$campains_id ]];
                $request_data=array_merge($request_data, $EM->getRepository('BroAppBundle:Phrase')->getStdYandexApiParams());
                $phrasesPacks=$YandexApi5->send5LimitedRequest('keywords', 'get', $request_data, [], 'Keywords');
                //dump($phrasesPacks);

                //Идем по пакам фраз
                if (!$YandexApi5->hasApiError() && !$phrasesPacks['errors'] && count($phrasesPacks['data'])) {
                    foreach ($phrasesPacks['data'] as $phrasePack) {
                        if (count($phrasePack['result'])) {
                            foreach ($phrasePack['result'] as $phrase) {

                                $Phrase=new Phrase();
                                $Phrase->fill($phrase)
                                    ->SetUser($AdGroups[$phrase['AdGroupId']]->getUser())
                                    ->SetYandexLogin($AdGroups[$phrase['AdGroupId']]->getYandexLogin())
                                    ->SetCampain($AdGroups[$phrase['AdGroupId']]->getCampain())
                                    ->SetAdGroup($AdGroups[$phrase['AdGroupId']])
                                    ->SetStrategy($AdGroups[$phrase['AdGroupId']]->getStrategy())
                                    ->setMaxPrice($AdGroups[$phrase['AdGroupId']]->getMaxPrice());

                                $Phrases[$phrase['Id']]=$Phrase;
                                $EM->persist($Phrase);

                            }
                        }
                    }

                    //Добавляем для них значение ставок
                    $request_data=[
                        'SelectionCriteria'=>['CampaignIds'=>$campains_id],
                        'FieldNames'=>['KeywordId', 'SearchPrices', 'MinSearchPrice', 'CurrentSearchPrice']
                    ];
                    $bidsPacks=$YandexApi5->send5LimitedRequest('bids', 'get', $request_data, [], 'Bids');

                    //Идем по пакам ставок
                    if (!$YandexApi5->hasApiError() && !$bidsPacks['errors'] && count($bidsPacks['data'])) {
                        foreach ($bidsPacks['data'] as $bidsPack) {
                            if (count($bidsPack['result'])) {
                                foreach ($bidsPack['result'] as $bid) {

                                    foreach($bid['SearchPrices'] as $searchPrice){
                                        if($searchPrice['Position']=='PREMIUMFIRST'){
                                            $Phrases[$bid['KeywordId']]->setPremiumMax($searchPrice['Price']/1000000);
                                        } else if($searchPrice['Position']=='PREMIUMBLOCK'){
                                            $Phrases[$bid['KeywordId']]->setPremiumMin($searchPrice['Price']/1000000);
                                        } else if($searchPrice['Position']=='FOOTERFIRST'){
                                            $Phrases[$bid['KeywordId']]->setMax($searchPrice['Price']/1000000);
                                        } else if($searchPrice['Position']=='FOOTERBLOCK'){
                                            $Phrases[$bid['KeywordId']]->setMin($searchPrice['Price']/1000000);
                                        }
                                    }

                                    $Phrases[$bid['KeywordId']]->setCurrentOnSearch($bid['CurrentSearchPrice']/1000000);
                                    $Phrases[$bid['KeywordId']]->setMinPrice($bid['MinSearchPrice']/1000000);

                                }
                            }
                        }
                    }

                }


                foreach($NewCampains as $NewCampain){
                    $NewCampain->setDataStatus('active');
                }


            }

            $EM->flush();



            //Если новые кампаниии у пользователя еще остались
            $userNewCampainsCount=$EM->getRepository('BroAppBundle:Campain')->findNewCampainsCountByUserId($NewYandexLogin->getUser()->getId());
            $yandexLoginNewCampainsCount=$EM->getRepository('BroAppBundle:Campain')->findNewCampainsCountByYandexLoginId($NewYandexLogin->getId());

            if(!$yandexLoginNewCampainsCount['n_new_campains']&&!$yandexLoginNewCampainsCount['n_upload_campains']){

                $NewYandexLogin->setStatus('active');
                $EM->flush();

            } /*else if($yandexLoginNewCampainsCount['n_new_campains']) {
            return $this->redirectToRoute('manage_new_campains', ['yandex_login'=>$NewYandexLogin]);
            }*/

            //$EM->clear();

            //возможны проблемы с большим расходом памяти
            //Поэтому стоит ограничитель редиректов по счетчику
            if($userNewCampainsCount['n_new_campains']&&$step<$max_steps){
                return $this->redirectToRoute('manage_new_campains', ['step'=>$step+1]);
            } else if ($step>=$max_steps){
                $Logger->info('Сработал ограничитель при загрузке новых логинов', ['step'=>$step]);
            }

        }

        $end_time=(microtime(true) - $start);
        $end_memory=memory_get_usage();


        if($Request->isXmlHttpRequest()){
            $this->AjaxResponse->setStatus('ok');
            return $this->AjaxResponse->getResponse();
        }

        return $this->render('BroAppBundle:StaticElements:test.html.twig', array('time'=>$end_time, 'memory'=>$end_memory-$start_memory));

    }




	//Загрузка данных для новых логинов
	//smart ищет кампании идобавляет к ним новые или удаляет старые
	//rewrite удаляет старые кампании и заносит все заного
/*	function loadNewLoginsDataAction($type='smart'){
		$Request=$this->getRequest();
		$EM = $this->getDoctrine()->getManager();
		$User = $this->getUser();


		if($Request->isXmlHttpRequest()){

			$YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findUserNewYandexLogins($User->getId());
			$this->LoadAllDataAction($YandexLogins, true, $type);

			$this->AjaxResponse->setData(array('href'=>$this->generateUrl('manage'),
																				 'workflow'=> $this->forward('BroAppBundle:Manage/Manage:manage', array('standalone'=>true))->getContent()), 'html', 'ok');

			return $this->AjaxResponse->getResponse();
		}

		return $this->render('BroAppBundle:Manage:/errors/loadNewLoginsData.html.twig');
	}*/



    //Новый метод обновления данных о логинах
    //и их клиентах
    public function loadClientsDataActionApi(){
        $Request=$this->getRequest();
        $EM = $this->getDoctrine()->getManager();
        $YandexApi = $this->get('yandex_api');
        $YandexApi5 = $this->get('yandex_api5');
        $Logger = $this->get('monolog.logger.bro');

        $start = microtime(true);
        $start_memory=memory_get_usage();
        $end_memory=$start_memory;

        set_time_limit(0);

        //ЗАМЕНИТЬ НА СПИСОК ЛОГИНОВ
        $AllYandexLogins=$EM->getRepository('BroAppBundle:yandexLogin')->findAllByLogin();
        $YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findOnlyRoot(true, 'working');
        $TimeStampFlag=$EM->getRepository('BroAppBundle:UserFlag')->findOneByName('yandex_logins_change_timestamp');
        $StdStartegy=$EM->getRepository('BroAppBundle:Strategy')->findFirstOne();


        //Если никогда метод не запускадся
        //ставим отметку времени отсчета изменений от имени нашего агенства
        //Я думаю можно удалить после тестов
        if(!$TimeStampFlag){
            $TimeStampFlag=new UserFlag();
            $OurAgencyLogin=$EM->getRepository('BroAppBundle:YandexLogin')->findOneByLogin($this->container->getParameter('our_yandex_login'));

            //$YandexApi->setToken($OurAgencyLogin->getToken());
            $YandexApi5->setToken5($OurAgencyLogin->getToken(), $OurAgencyLogin);

            /*$result=$YandexApi->sendRequest('GetChanges', [], 'data');

            if(!$YandexApi->hasApiError()){
                $TimeStampFlag->setName('yandex_logins_change_timestamp')
                    ->setValue($result['Timestamp'])
                    ->setDateValue(new \DateTime('now'));
                $EM->persist($TimeStampFlag);
            }*/

            $timestamp=$YandexApi5->send5Request('changes', 'checkDictionaries', (object) [], [], 'Timestamp');
            if(!$YandexApi5->hasApiError()){
                $TimeStampFlag->setName('yandex_logins_change_timestamp')
                    ->setValue($timestamp)
                    ->setDateValue(new \DateTime('now'));
                $EM->persist($TimeStampFlag);
            }

        }

        //Если есть логины и метка отсчета
        //идем по списку логинов
        //и чекаем изменения
        if($YandexLogins&&$TimeStampFlag->getValue()){
            foreach($YandexLogins as $YandexLogin){

                $YandexApi->setToken($YandexLogin->getToken());

                //Для агенства еще проверяем клиентов
                //и загружаем новых
                if($YandexLogin->getRole()=='Agency'&&count($YandexLogin->getSubLogins())){
                    $clients_list=[];

                    //формируем список логинов для массового запроса
                    foreach($YandexLogin->getSubLogins() as $Client){
                        $clients_list[]=$Client->getLogin();
                    }

                    //Проверяем логины на изменения
                    $result=$YandexApi->sendRequest('GetChanges', ['Logins'=>$clients_list,
                    'Timestamp'=>$TimeStampFlag->getValue()], 'data');
                    //dump($result);

                    if(!$YandexApi->hasApiError()&&count($result['Logins']['Updated'])){

                        //Получаем данные измененных и
                        //обновляем их данные в БД
                        $clients_info_packs=$YandexApi->sendLimitedRequest('GetClientInfo', $result['Logins']['Updated'], 'data');
                        if(!$YandexApi->hasApiError()&&!$clients_info_packs['errors']){

                            //Идем по пакам так как запрос лимиторованный
                            foreach($clients_info_packs['data'] as $clients_info_pack){
                                foreach($clients_info_pack['result'] as $client_info){
                                    if(isset($YandexLogin->getSubLogins()[$client_info['Login']])){
                                        $YandexLogin->getSubLogins()[$client_info['Login']]->fill($client_info)
                                            ->setBullets($client_info);
                                    }
                                }
                            }
                        }
                    }

                    //Добавление новых логинов
                    $clients_list=$YandexApi->sendRequest('GetClientsList', [], 'data');
                    if(!$YandexApi->hasApiError()&&count($clients_list)){
                        foreach($clients_list as $client){

                            //Если нет проверяем есть ли он в системе
                            //только если нет добавляем его
                            //сделано что бы избежать дублей логинов созданных для нашего агенства из системы
                            //так так они хранятся отдельно не привязынными к паренту - нашему агенству
                            if(!isset($AllYandexLogins[$client['Login']])){
                                $SubYandexLogin=new YandexLogin();
                                $SubYandexLogin->fill($client)
                                    ->setBullets($client)
                                    ->setLogin($client['Login'])
                                    ->setStatus('new')
                                    ->SetDateCreate(new \DateTime($client['DateCreate']))
                                    ->setParentLogin($YandexLogin)
                                    ->setUser($YandexLogin->getUser())
                                    ->setAccess(1);

                                //И загружаем их кампании
                                $campains_list=$YandexApi->sendRequest('GetCampaignsList', [$SubYandexLogin->getLogin()], 'data');

                                if(!$YandexApi->hasApiError()&&is_array($campains_list)){

                                    //Заполняем кампании
                                    foreach($campains_list as $campain){
                                        $Campain=new Campain();
                                        $Campain->fill($campain)
                                            ->SetUser($SubYandexLogin->getUser())
                                            ->SetYandexLogin($SubYandexLogin)
                                            ->setDataStatus('active')
                                            ->SetStrategy($StdStartegy)
                                            ->setMaxPrice(0)
                                            ->SetStartDate(new \DateTime($campain['StartDate']));

                                        $SubYandexLogin->addCampain($Campain);
                                    }

                                }
                                $YandexLogin->addSubLogin($SubYandexLogin);
                            }
                        }
                    }


                }

                //Проверяем корневые логина на изменения
                //и нужные правим в БД
                $result=$YandexApi->sendRequest('GetChanges', ['Logins'=>[$YandexLogin->getLogin()],
                'Timestamp'=>$TimeStampFlag->getValue()], 'data');
                if(!$YandexApi->hasApiError()&&count($result['Logins']['Updated'])){
                    foreach($result['Logins']['Updated'] as $yandex_login){
                        $client_info=$YandexApi->sendRequest('GetClientInfo', [$yandex_login], 'single');
                        if(!$YandexApi->hasApiError()){
                            $YandexLogin->fill($client_info)
                                ->setBullets($client_info);
                        }
                    }

                    //сохраняем новый тайм стамп
                    $TimeStampFlag->setValue($result['Timestamp']);

                }

            }
        }

        $EM->flush();


        $end_time=(microtime(true) - $start);
        $end_memory=memory_get_usage();
        return $this->render('BroAppBundle:StaticElements:test.html.twig', array('time'=>$end_time, 'memory'=>$end_memory-$start_memory));
    }


    public function loadClientsDataAction5(){

        $Request=$this->getRequest();
        $EM = $this->getDoctrine()->getManager();
        $YandexApi5 = $this->get('yandex_api5');
        $Logger = $this->get('monolog.logger.bro');

        $start = microtime(true);
        $start_memory=memory_get_usage();
        $end_memory=$start_memory;

        set_time_limit(0);

        //ЗАМЕНИТЬ НА СПИСОК ЛОГИНОВ
        $AllYandexLogins=$EM->getRepository('BroAppBundle:yandexLogin')->findAllByLogin();
        $YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findOnlyRoot(true, 'working');
        $StdStartegy=$EM->getRepository('BroAppBundle:Strategy')->findFirstOne();



        //Если есть логины
        //идем по списку логинов
        //и чекаем изменения
        if($YandexLogins){
            foreach($YandexLogins as $YandexLogin){

                $YandexApi5->setToken5($YandexLogin->getToken(), $YandexLogin);

                //Для агенства еще проверяем клиентов
                //и загружаем новых
                if($YandexLogin->getRole()=='Agency'&&count($YandexLogin->getSubLogins())){

                    $request_data=$EM->getRepository('BroAppBundle:Campain')->getStdYandexApiParams();
                    $clients =$YandexApi5->send5Request('clients', 'get', $request_data, [], 'Clients');
                    if(!$YandexApi5->hasApiError()){

                        foreach ($clients as $client) {

                            //Если клиент есть редактируем
                            if(isset($YandexLogin->getSubLogins()[$client['Login']])){
                                $YandexLogin->getSubLogins()[$client['Login']]->fill($client)
                                    ->setBullets($client);

                            //Если нет - добавляем
                            } else {

                                $SubYandexLogin=new YandexLogin();

                                $SubYandexLogin->fill($client)
                                    ->setBullets($client)
                                    ->setLogin($client['Login'])
                                    ->setStatus('new')
                                    ->setParentLogin($YandexLogin)
                                    ->setUser($YandexLogin->getUser())
                                    ->setAccess(1);

                                $YandexLogin->addSubLogin($SubYandexLogin);


                                $YandexApi5->setToken5($YandexLogin->getToken(), $SubYandexLogin);

                                $request_data=['SelectionCriteria'=>['Types'=>['TEXT_CAMPAIGN']]];
                                $request_data=array_merge($request_data, $EM->getRepository('BroAppBundle:Campain')->getStdYandexApiParams());

                                $campains_list=$YandexApi5->send5Request('campaigns', 'get', $request_data, [], 'Campaigns');
                                if(!$YandexApi5->hasApiError()&&is_array($campains_list)){

                                    //Заполняем кампании
                                    foreach($campains_list as $campain){
                                        //dump($campain);
                                        $Campain=new Campain();
                                        $Campain->fill($campain)
                                            ->SetUser($SubYandexLogin->getUser())
                                            ->SetYandexLogin($SubYandexLogin)
                                            ->setDataStatus('new')
                                            ->SetStrategy($StdStartegy)
                                            ->setMaxPrice(0)
                                            ->SetStartDate(new \DateTime($campain['StartDate']));

                                        $SubYandexLogin->addCampain($Campain);
                                    }
                                }

                            }
                        }
                    }
                }

                //Проверяем корневые логина на изменения
                //и нужные правим в БД
                $YandexApi5->setToken5($YandexLogin->getToken());

                $result=$YandexApi->sendRequest('GetChanges', ['Logins'=>[$YandexLogin->getLogin()],
                    'Timestamp'=>$TimeStampFlag->getValue()], 'data');
                if(!$YandexApi->hasApiError()&&count($result['Logins']['Updated'])){
                    foreach($result['Logins']['Updated'] as $yandex_login){
                        $client_info=$YandexApi->sendRequest('GetClientInfo', [$yandex_login], 'single');
                        if(!$YandexApi->hasApiError()){
                            $YandexLogin->fill($client_info)
                                ->setBullets($client_info);
                        }
                    }

                    //сохраняем новый тайм стамп
                    $TimeStampFlag->setValue($result['Timestamp']);

                }

            }
        }

        $EM->flush();


        $end_time=(microtime(true) - $start);
        $end_memory=memory_get_usage();
        return $this->render('BroAppBundle:StaticElements:test.html.twig', array('time'=>$end_time, 'memory'=>$end_memory-$start_memory));
    }



  public function loadCampainsDataAction($step=1){
      $Request=$this->getRequest();
      $EM = $this->getDoctrine()->getManager();
      $YandexApi = $this->get('yandex_api');
      $YandexApi5 = $this->get('yandex_api5');
      $Logger = $this->get('monolog.logger.update');

      set_time_limit(0);

      $start = microtime(true);
      $start_memory=memory_get_usage();
      $end_memory=$start_memory;

      //Эти параметры можно варьировать в зависимости от сервера
      $max_steps=$this->container->getParameter('load_campains_n_steps');
      //$max_steps=1;
      $maxYandexLogins=$this->container->getParameter('load_campains_n_items');
      //$maxYandexLogins=1000;

      $YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findClients(['working', 'data_active'], $maxYandexLogins);
      //$YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findByLogin('kmklink');

      $StdStartegy=$EM->getRepository('BroAppBundle:Strategy')->findFirstOne();
      $NewPhrase = new Phrase();


      $YandexLoginscf=$EM->getRepository('BroAppBundle:YandexLogin')->findOneByLogin('odincow');
      // $YandexLogins=[$YandexLoginscf];

      //return $this->render('BroAppBundle:StaticElements:test.html.twig');

      if(!count($YandexLogins)){
          $EM->getRepository('BroAppBundle:YandexLogin')->activateYandexLogins();
          $YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findClients(['working', 'data_active'], $maxYandexLogins);
      }
      //$YandexLogins=$EM->getRepository('BroAppBundle:YandexLogin')->findById(903);


      if (count($YandexLogins)){
          //Помечаем что объявления обновляются
          //так как процесс долгий и что бы не было наложений
          foreach($YandexLogins as $YandexLogin){
              $YandexLogin->setDataStatus('upload');
          }
          $EM->flush();


          foreach($YandexLogins as $YandexLogin) {

              echo $YandexLogin->getLogin() . '---';
              //$Logger->info('Обновление логина', ['$YandexLogin->getLogin('=>$YandexLogin->getLogin()]);

              $YandexApi->setToken($YandexLogin->getToken());
              $YandexApi5->setToken5($YandexLogin->getToken(), $YandexLogin);
              $campaignIDs = [];
              //$adGroupsIDs=[];
              $bannerIDs = [];
              $timeStamp=false;


              //немного спорное решение конечно выборка в цикле
              //но по идее так память будет сильно экономится
              //$campaignIDs=$EM->getRepository('BroAppBundle:Campain')->findYandexLoginCampaignIDs($YandexLogin->getId());

              //ВНИМАНИЕ
              //ЗАГРУЖАЕМ НЕ ВСЕ ДАННЫЕ СУЩНОСТЕЙ ДЛЯ ЭКОНОМИИ
              //Добавить ['id', 'AdGroupID', 'MaxPrice', 'Strategy']
              //Ограничить поля к сожалению нельзя
              //ебучая доктрина не сохраняет неполную сущьность
              //и даже если выбрать потом отдельно эта твать ебучая берет данные из кеша а не БД
              $Campains = $EM->getRepository('BroAppBundle:Campain')->findByYandexLoginId($YandexLogin->getId(), false, false);

              if(is_array($Campains)&&count($Campains)){

                  //Выбираем идишники кампаний и баннеров в БД
                  foreach ($Campains as $Campain) {
                      $campaignIDs[] = $Campain->getCampaignID();
                  }

                  //Если никогда обновление не запускалось
                  //ставим отметку времени отсчета изменений
                  if (!$YandexLogin->getChangeCampainsTimeStamp()) {
                     /* $result = $YandexApi->sendRequest('GetChanges', [], 'data');
                      if (!$YandexApi->hasApiError()) {
                          $YandexLogin->setChangeCampainsTimeStamp($result['Timestamp']);
                      }*/

                      $timestamp=$YandexApi5->send5Request('changes', 'checkDictionaries', (object) [], [], 'Timestamp');
                      if(!$YandexApi5->hasApiError()){
                          $YandexLogin->setChangeCampainsTimeStamp($timestamp);
                      }


                  }

                  $actual_campaignIDs = false;

                  //Получаем список кампаний
                  //$campains_list = $YandexApi->sendRequest('GetCampaignsList', [$YandexLogin->getLogin()], 'data');


                  //Получаем изменения в кампанийх
                  $changedCampaignIDs=['updated'=>false, 'added'=>false, 'children_changed'=>false];
                  $checkCampains=$YandexApi5->send5Request('changes', 'checkCampaigns', ['Timestamp'=>$YandexLogin->getChangeCampainsTimeStamp()], []);

                  if(!$YandexApi5->hasApiError()){

                      if(!$timeStamp){
                          $timeStamp=$checkCampains['Timestamp'];
                      }

                      if(isset($checkCampains['Campaigns'])){
                          foreach($checkCampains['Campaigns'] as $changedCampain){

                              //Если кампания есть в БД
                              //и она изменилась сохраняем её для редактирования
                              if (isset($Campains[$changedCampain['CampaignId']])){

                                  if(array_search('SELF', $changedCampain['ChangesIn'])!==false){
                                      $changedCampaignIDs['updated'][]=$changedCampain['CampaignId'];
                                  }

                                  if(array_search('CHILDREN', $changedCampain['ChangesIn'])!==false){
                                      $changedCampaignIDs['children_changed'][]=$changedCampain['CampaignId'];
                                  }


                              //Если кампаниии нет сохраняем её для добавления
                              } else {
                                  $changedCampaignIDs['added'][]=$changedCampain['CampaignId'];
                                  $changedCampaignIDs['children_changed'][]=$changedCampain['CampaignId'];
                              }

                          }

                      }




                      //Можн было бы объединить все айдишники и взять 1 запросом,
                      //но думаю так будет лучше и нагляжней
                      if($changedCampaignIDs['updated']){
                          $request_data=[
                              'SelectionCriteria'=>['Ids'=>$changedCampaignIDs['updated']],
                          ];
                          $request_data=array_merge($request_data, $EM->getRepository('BroAppBundle:Campain')->getStdYandexApiParams());
                          $campains_list =$YandexApi5->send5Request('campaigns', 'get', $request_data, [], 'Campaigns');
                          if(!$YandexApi5->hasApiError()){
                              foreach ($campains_list as $campain) {
                                  $Campains[$campain['Id']]->fill($campain);
                                  $Logger->info('Редактирование кампании для логина ' . $YandexLogin->getLogin(), ['ID' => $campain['Id'], 'Name' => $campain['Name']]);
                              }
                          }
                      }

                      if($changedCampaignIDs['added']){
                          $request_data=[
                              'SelectionCriteria'=>['Ids'=>$changedCampaignIDs['added']],
                          ];
                          $request_data=array_merge($request_data, $EM->getRepository('BroAppBundle:Campain')->getStdYandexApiParams());
                          $campains_list =$YandexApi5->send5Request('campaigns', 'get', $request_data, [], 'Campaigns');
                          if(!$YandexApi5->hasApiError()){
                              foreach ($campains_list as $campain) {
                                  $Campain = new Campain();
                                  $Campain->fill($campain)
                                      ->SetUser($YandexLogin->getUser())
                                      ->SetYandexLogin($YandexLogin)
                                      ->SetStrategy($StdStartegy)
                                      ->setDataStatus('active')
                                      ->setMaxPrice(0)
                                      ->SetStartDate(new \DateTime($campain['StartDate']));

                                  $Campains[$campain['Id']]=$Campain;
                                  $EM->persist($Campain);
                                  $Logger->info('Добавление кампании для логина ' . $YandexLogin->getLogin(), ['ID' => $campain['Id'], 'Name' => $campain['Name']]);
                              }
                          }
                      }
                  }

                  //Проверяем на удаление
                  $checkCampains=$YandexApi5->send5Request('changes', 'check', [
                      'CampaignIds'=>$campaignIDs,
                      'FieldNames'=>['CampaignIds'],
                      'Timestamp'=>$YandexLogin->getChangeCampainsTimeStamp()
                  ], [], 'NotFound');

                  if(!$YandexApi5->hasApiError()) {

                      if(!$timeStamp){
                          $timeStamp=$checkCampains['Timestamp'];
                      }

                      if (isset($checkCampains['CampaignIds'])) {
                          foreach ($checkCampains['CampaignIds'] as $deletedCampainId) {

                              $Logger->info('УДАЛЕНИЕ кампании для логина ' . $YandexLogin->getLogin(), ['ID' => $deletedCampainId, 'Name' => $Campains[$deletedCampainId]->getName(), 'campaignIDs' => $campaignIDs]);

                              //ВРЕМЕННО. До установления причин наебалова с паразиным удалением
                              $EM->remove($Campains[$deletedCampainId]);
                          }

                      }
                  }

                  //dump($YandexApi5->getError());
                  //dump($timeStamp);


                  if($changedCampaignIDs['children_changed']){

                      //Берем измененные
                      $checkCampainsClildrens=$YandexApi5->send5Request('changes', 'check', [
                          'CampaignIds'=>$changedCampaignIDs['children_changed'],
                          'FieldNames'=>['AdGroupIds','AdIds'],
                          'Timestamp'=>$YandexLogin->getChangeCampainsTimeStamp()
                      ], []);

                      //Берем из БД
                      $AdGroups = $EM->getRepository('BroAppBundle:AdGroup')->findByCampaignID($changedCampaignIDs['children_changed']);
                      $Banners = $EM->getRepository('BroAppBundle:Banner')->findByCampaignID($changedCampaignIDs['children_changed']);

                        //dump(count($AdGroups));
                      if(isset($checkCampainsClildrens['Modified'])){

                          if(!$timeStamp){
                              $timeStamp=$checkCampainsClildrens['Timestamp'];
                          }

                          //РАБОТА С ГРУППАМИ ОБЪЯВЛЕНИЙ
                          $newAdGroupsIDs=false;
                          if(isset($checkCampainsClildrens['Modified']['AdGroupIds'])){

                              //Берем из АПИ измененные
                              $request_data=['SelectionCriteria'=>[
                                      'Ids'=>$checkCampainsClildrens['Modified']['AdGroupIds'],
                                      'Types'=>['TEXT_AD_GROUP'],
                                  ]
                              ];
                              $request_data=array_merge($request_data, $EM->getRepository('BroAppBundle:AdGroup')->getStdYandexApiParams());
                              $adGroupsPacks=$YandexApi5->send5LimitedRequest('AdGroups', 'get', $request_data, [], 'AdGroups');

                              //Идем по пакам групп
                              if (!$YandexApi5->hasApiError() && !$adGroupsPacks['errors'] && count($adGroupsPacks['data'])) {
                                  foreach ($adGroupsPacks['data'] as $adGroupsPack) {
                                      if (count($adGroupsPack['result'])) {
                                          foreach ($adGroupsPack['result'] as $changedAdGroup) {

                                              //Если они в системе - редактируем
                                              if(isset($AdGroups[$changedAdGroup['Id']])){

                                                  $AdGroups[$changedAdGroup['Id']]->fill($changedAdGroup);

                                              //Если их нет - добавляем
                                              } else {

                                                  $AdGroup = new AdGroup();

                                                  $AdGroup->fill($changedAdGroup)
                                                      ->SetUser($Campains[(string) $changedAdGroup['CampaignId']]->getUser())
                                                      ->SetYandexLogin($Campains[(string) $changedAdGroup['CampaignId']]->getYandexLogin())
                                                      ->SetCampain($Campains[(string) $changedAdGroup['CampaignId']])
                                                      ->SetStrategy($Campains[(string) $changedAdGroup['CampaignId']]->getStrategy())
                                                      ->setMaxPrice($Campains[(string) $changedAdGroup['CampaignId']]->getMaxPrice());

                                                  $AdGroups[$changedAdGroup['Id']]=$AdGroup;
                                                  $newAdGroupsIDs[]=$changedAdGroup['Id'];
                                                  $EM->persist($AdGroup);

                                                  //$Campains[(string) $changedAdGroup['CampaignId']]->addAdgroup($AdGroup, $changedAdGroup['Id']);
                                                  $Logger->info('Добавление группы объявлений для логина ' . $YandexLogin->getLogin(), ['ID' => $changedAdGroup['Id'], 'AdGroupName' => $changedAdGroup['Name']]);




                                              }
                                          }
                                      }
                                  }
                              }


                              //Для новых групп сразу добавляем фразы
                              if($newAdGroupsIDs){

                                  $Phrases=[];
                                  $request_data=['SelectionCriteria'=>['AdGroupIds'=>$newAdGroupsIDs ]];
                                  $request_data=array_merge($request_data, $EM->getRepository('BroAppBundle:Phrase')->getStdYandexApiParams());
                                  $phrasesPacks=$YandexApi5->send5LimitedRequest('keywords', 'get', $request_data, [], 'Keywords');


                                  //Идем по пакам фраз
                                  if (!$YandexApi5->hasApiError() && !$phrasesPacks['errors'] && count($phrasesPacks['data'])) {
                                      foreach ($phrasesPacks['data'] as $phrasePack) {
                                          if (count($phrasePack['result'])) {
                                              foreach ($phrasePack['result'] as $phrase) {

                                                  $Phrase=new Phrase();
                                                  $Phrase->fill($phrase)
                                                      ->SetUser($AdGroups[$phrase['AdGroupId']]->getUser())
                                                      ->SetYandexLogin($AdGroups[$phrase['AdGroupId']]->getYandexLogin())
                                                      ->SetCampain($AdGroups[$phrase['AdGroupId']]->getCampain())
                                                      ->SetAdGroup($AdGroups[$phrase['AdGroupId']])
                                                      ->SetStrategy($AdGroups[$phrase['AdGroupId']]->getStrategy())
                                                      ->setMaxPrice($AdGroups[$phrase['AdGroupId']]->getMaxPrice());

                                                  $Phrases[$phrase['Id']]=$Phrase;
                                                  $EM->persist($Phrase);

                                              }
                                          }
                                      }

                                      //Добавляем для них значение ставок
                                      $request_data=['SelectionCriteria'=>[
                                          'AdGroupIds'=>$newAdGroupsIDs],
                                          'FieldNames'=>['KeywordId', 'SearchPrices', 'MinSearchPrice', 'CurrentSearchPrice']
                                      ];
                                      $bidsPacks=$YandexApi5->send5LimitedRequest('bids', 'get', $request_data, [], 'Bids');

                                      //Идем по пакам ставок
                                      if (!$YandexApi5->hasApiError() && !$bidsPacks['errors'] && count($bidsPacks['data'])) {
                                          foreach ($bidsPacks['data'] as $bidsPack) {
                                              if (count($bidsPack['result'])) {
                                                  foreach ($bidsPack['result'] as $bid) {

                                                      foreach($bid['SearchPrices'] as $searchPrice){
                                                          if($searchPrice['Position']=='PREMIUMFIRST'){
                                                              $Phrases[$bid['KeywordId']]->setPremiumMax($searchPrice['Price']/1000000);
                                                          } else if($searchPrice['Position']=='PREMIUMBLOCK'){
                                                              $Phrases[$bid['KeywordId']]->setPremiumMin($searchPrice['Price']/1000000);
                                                          } else if($searchPrice['Position']=='FOOTERFIRST'){
                                                              $Phrases[$bid['KeywordId']]->setMax($searchPrice['Price']/1000000);
                                                          } else if($searchPrice['Position']=='FOOTERBLOCK'){
                                                              $Phrases[$bid['KeywordId']]->setMin($searchPrice['Price']/1000000);
                                                          }
                                                      }

                                                      $Phrases[$bid['KeywordId']]->setCurrentOnSearch($bid['CurrentSearchPrice']/1000000);
                                                      $Phrases[$bid['KeywordId']]->setMinPrice($bid['MinSearchPrice']/1000000);

                                                  }
                                              }
                                          }
                                      }

                                  }

                              }

                          }
                          //dump(count($AdGroups));

                          //РАБОТА С ОБЪЯВЛЕНИЯМИ
                          if(isset($checkCampainsClildrens['Modified']['AdIds'])){

                              //Берем из АПИ измененные
                              $request_data=['SelectionCriteria'=>['Ids'=>$checkCampainsClildrens['Modified']['AdIds'] ]];
                              $request_data=array_merge($request_data, $EM->getRepository('BroAppBundle:Banner')->getStdYandexApiParams());

                              $bannersPacks=$YandexApi5->send5LimitedRequest('Ads', 'get', $request_data, [], 'Ads');

                              //Идем по пакам объявлений
                              if (!$YandexApi5->hasApiError() && !$bannersPacks['errors'] && count($bannersPacks['data'])) {
                                  foreach ($bannersPacks['data'] as $bannersPack) {
                                      if (count($bannersPack['result'])) {
                                          foreach ($bannersPack['result'] as $changedBanner) {

                                              //Если они в системе - редактируем
                                              if(isset($Banners[$changedBanner['Id']])){

                                                  $Banners[$changedBanner['Id']]->fill($changedBanner);

                                              //Если их нет - добавляем
                                              } else {

                                                  $Banner = new Banner();

                                                  $Banner->fill($changedBanner)
                                                      ->SetUser($AdGroups[$changedBanner['AdGroupId']]->getUser())
                                                      ->SetYandexLogin($AdGroups[$changedBanner['AdGroupId']]->getYandexLogin())
                                                      ->SetCampain($AdGroups[$changedBanner['AdGroupId']]->getCampain())
                                                      ->SetAdGroup($AdGroups[$changedBanner['AdGroupId']]);

                                                  //Он же не используется дальше, в отличие от группы, нахнагружать
                                                  //$Banners[$changedBanner['Id']][]=$Banner;
                                                  $EM->persist($Banner);

                                                  //$Campains[(string) $changedBanner['CampaignId']]->addAdgroup($AdGroup, $changedBanner['Id']);
                                                  $Logger->info('Добавление объявления для логина ' . $YandexLogin->getLogin(), ['BannerID' => $changedBanner['Id'], 'AdGroupName' => $Banner->getText() ]);

                                              }
                                          }
                                      }
                                  }
                              }

                          }


                      }


                      //Проверяем на удаление группы
                      if(count($AdGroups)){

                          $adGroupsIDs=[];

                          foreach($AdGroups as $AdGroup){
                              $adGroupsIDs[]=$AdGroup->getAdGroupID();
                          }

                          $checkAdGroups=$YandexApi5->send5Request('changes', 'check', [
                              'AdGroupIds'=>$adGroupsIDs,
                              'FieldNames'=>['AdGroupIds'],
                              'Timestamp'=>$YandexLogin->getChangeCampainsTimeStamp()
                          ], [], 'NotFound');

                          if(!$YandexApi5->hasApiError()) {

                              if(!$timeStamp){
                                  $timeStamp=$checkAdGroups['Timestamp'];
                              }

                              if (isset($checkAdGroups['AdGroupIds'])) {
                                  foreach ($checkAdGroups['AdGroupIds'] as $deletedAdGroupId) {

                                      $Logger->info('УДАЛЕНИЕ кампании для логина ' . $YandexLogin->getLogin(), ['ID' => $deletedAdGroupId, 'Name' => $AdGroups[$deletedAdGroupId]->getAdGroupName()]);

                                      //ВРЕМЕННО. До установления причин наебалова с паразиным удалением
                                      $EM->remove($AdGroups[$deletedAdGroupId]);
                                  }

                              }
                          }

                      }

                      //Проверяем на удаление объявления
                      if(count($Banners)){

                          $bannersIDs=[];

                          foreach($Banners as $Banner){
                              $bannersIDs[]=$Banner->getBannerID();
                          }

                          $checkBanners=$YandexApi5->send5Request('changes', 'check', [
                              'AdIds'=>$bannersIDs,
                              'FieldNames'=>['AdIds'],
                              'Timestamp'=>$YandexLogin->getChangeCampainsTimeStamp()
                          ], [], 'NotFound');

                          if(!$YandexApi5->hasApiError()) {

                              if(!$timeStamp){
                                  $timeStamp=$checkBanners['Timestamp'];
                              }

                              if (isset($checkBanners['AdIds'])) {
                                  foreach ($checkBanners['AdIds'] as $deletedBannerId) {

                                      $Logger->info('УДАЛЕНИЕ объявления для логина ' . $YandexLogin->getLogin(), ['ID' => $deletedBannerId, 'Title' => $Banners[$deletedBannerId]->getTitle()]);

                                      //ВРЕМЕННО. До установления причин наебалова с паразиным удалением
                                      $EM->remove($Banners[$deletedBannerId]);
                                  }

                              }
                          }

                      }

                  }


                  $YandexLogin->setDataStatus('actual');
                  if($timeStamp){
                      $YandexLogin->setChangeCampainsTimeStamp($timeStamp);
                  }

                  //Здесь потому что если использовать вставку фраз напрямую
                  //то для нового баннера еще не будет сформирован id
                  $EM->flush();


              }

          }

          $EM->flush();
          $EM->clear();

          $end_time=(microtime(true) - $start);
          $end_memory=memory_get_usage();

          $Logger->info('Шаг по импорту данных с яндекса. Время выполнения: '.round($end_time, 2).' сек. Съеденая память: '.($end_memory-$start_memory).' Б', []);



          //возможны проблемы с большим расходом памяти
          //Поэтому стоит ограничитель редиректов по счетчику

          if($step<$max_steps){
              return $this->redirectToRoute('load_campains_data', ['step'=>$step+1]);
          } else if ($step>=$max_steps){
              $Logger->info('Сработал ограничитель при загрузке новых логинов', ['step'=>$step]);
          }

      }

      $end_time=(microtime(true) - $start);
      $end_memory=memory_get_usage();

      return $this->render('BroAppBundle:StaticElements:test.html.twig', array('time'=>$end_time, 'memory'=>$end_memory-$start_memory));
  }



    public function updatePhrasesDataAction($step=1){

        $Request=$this->getRequest();
        $EM = $this->getDoctrine()->getManager();
        $YandexApi5 = $this->get('yandex_api5');
        $Logger = $this->get('monolog.logger.update');

        set_time_limit(0);

        $start = microtime(true);
        $start_memory=memory_get_usage();
        $end_memory=$start_memory;

        //Эти параметры можно варьировать в зависимости от сервера
        $max_steps=$this->container->getParameter('load_phrases_n_steps');
        //$max_steps=1;
        $maxAdGroups=$this->container->getParameter('load_phrases_n_items');
        $updatePauseInterval='-'.$this->container->getParameter('load_phrases_pause').' minutes';
        //$updatePauseInterval='-0 minutes';
        //$maxAdGroups=50000;

        //$AdGroups=$EM->getRepository('BroAppBundle:AdGroup')->findWithFilter(['working', 'data_active'], $maxAdGroups, $updatePauseInterval);
        //$AdGroups=$EM->getRepository('BroAppBundle:AdGroup')->findWithFilter(['live', 'data_active'], $maxAdGroups, $updatePauseInterval);
        $AdGroups=$EM->getRepository('BroAppBundle:AdGroup')->findWithFilter(['live', 'data_active'], $maxAdGroups);

        //$AdGroups['266499532']=$EM->getRepository('BroAppBundle:AdGroup')->findOneById(274161);
        if(!count($AdGroups)){
            $EM->getRepository('BroAppBundle:AdGroup')->activateAdGroup();
            //$AdGroups=$EM->getRepository('BroAppBundle:AdGroup')->findWithFilter(['live', 'data_active'], $maxAdGroups, $updatePauseInterval);
            $AdGroups=$EM->getRepository('BroAppBundle:AdGroup')->findWithFilter(['live', 'data_active'], $maxAdGroups);
        }

        //$AdGroups['124655860']=$EM->getRepository('BroAppBundle:AdGroup')->findOneById(287863);
        //dump($AdGroups);

        $newCheckPhrasesTimeStamp=false;
        if (count($AdGroups)) {
            $adGroupsIDs = [];


            //dump($AdGroups);
            reset($AdGroups);
            $first_key = key($AdGroups);
            $minCheckPhrasesTime = strtotime($AdGroups[$first_key]->getCheckPhrasesTimeStamp());
            $minCheckPhrasesTimeStamp = $AdGroups[$first_key]->getCheckPhrasesTimeStamp();

            //Помечаем что объявления обновляются
            //так как процесс долгий и что бы не было наложений
            foreach ($AdGroups as $key => $AdGroup) {
                //$checkPhrasesTimeStamps[]=strtotime($AdGroup->getCheckPhrasesTimeStamp());
                //$AdGroup->setDataStatus('upload');

                //Можно улучшить, собирая таймстампы отдельно для каждого логина
                $checkPhrasesTime = strtotime($AdGroup->getCheckPhrasesTimeStamp());
                if ($checkPhrasesTime < $minCheckPhrasesTime) {
                    $minCheckPhrasesTime = $checkPhrasesTime;
                    $minCheckPhrasesTimeStamp = $AdGroup->getCheckPhrasesTimeStamp();
                }
                $adGroupsIDs[$AdGroup->getYandexLogin()->getId()][] = $AdGroup->getAdGroupID();
                //echo '-'.$AdGroup->getAdGroupID().'-<br/>';
                $Logins[$AdGroup->getYandexLogin()->getId()] = $AdGroup->getYandexLogin();
            }

            foreach ($adGroupsIDs as $yandexLoginId => $yandexLoginAdGroupsIDs) {
                $EM->getRepository('BroAppBundle:AdGroup')->updateAdGroupStatus('upload', $yandexLoginAdGroupsIDs);
            }


            foreach ($adGroupsIDs as $yandexLoginId => $yandexLoginAdGroupsIDs) {

                $YandexApi5->setToken5($Logins[$yandexLoginId]->getToken(), $Logins[$yandexLoginId]);


                //$NewPhraseYandexLogin=$EM->getRepository('BroAppBundle:YandexLogin')->findOneById($yandexLoginId);
                //$NewPhraseUser=$NewPhraseYandexLogin->getUser();

                $Phrases = $EM->getRepository('BroAppBundle:Phrase')->findByAdGroupsIDs($yandexLoginAdGroupsIDs);
                $newPhrasesIDs = false;
                $actualPhrasesIDs = false;


                //Берем измененные
                $checkAdGroupsPacks = $YandexApi5->send5LimitedRequest('changes', 'check', [
                    'AdGroupIds' => $yandexLoginAdGroupsIDs,
                    'FieldNames' => ['AdGroupIds'],
                    'Timestamp' => $minCheckPhrasesTimeStamp
                ], []);

                //dump($yandexLoginAdGroupsIDs);
                //dump($Logins[$yandexLoginId]->getLogin());

                //dump($phrases);
                //dump($checkAdGroupsPacks);

                $newPhrasesIDs = false;
                $actualPhrasesIDs = false;
                if (!$YandexApi5->hasApiError() && !$checkAdGroupsPacks['errors'] && count($checkAdGroupsPacks['data'])) {
                    foreach ($checkAdGroupsPacks['data'] as $checkAdGroupsPack) {
                        if (count($checkAdGroupsPack['result'])) {

                            if (!$newCheckPhrasesTimeStamp) {
                                $newCheckPhrasesTimeStamp = $checkAdGroupsPack['result']['Timestamp'];
                            }
                            //dump($checkAdGroupsPack['result']);
                            if ($checkAdGroupsPack['result']['Modified']) {

                                $request_data = ['SelectionCriteria' => ['AdGroupIds' => $checkAdGroupsPack['result']['Modified']['AdGroupIds']]];
                                //$request_data = ['SelectionCriteria' => ['AdGroupIds' => $yandexLoginAdGroupsIDs]];
                                $request_data = array_merge($request_data, $EM->getRepository('BroAppBundle:Phrase')->getStdYandexApiParams());
                                $phrasesPacks = $YandexApi5->send5LimitedRequest('keywords', 'get', $request_data, [], 'Keywords');

                                //Идем по пакам фраз
                                if (!$YandexApi5->hasApiError() && !$phrasesPacks['errors'] && count($phrasesPacks['data'])) {
                                    foreach ($phrasesPacks['data'] as $phrasePack) {
                                        if (count($phrasePack['result'])) {
                                            foreach ($phrasePack['result'] as $phrase) {

                                                $actualPhrasesIDs[] = $phrase['Id'];

                                                //Если фраза есть в БД - редактируем
                                                if (isset($Phrases[$phrase['Id']])) {

                                                    if (!$this->get('bro.compare')->comparePhrases($phrase, $Phrases[$phrase['Id']])) {
                                                        $Phrases[$phrase['Id']]->fill($phrase);
                                                    }

                                                    //Добавляем
                                                } else {

                                                    $NewPhrase = new Phrase();
                                                    $NewPhrase->fill($phrase)
                                                        ->SetUser($AdGroups[$phrase['AdGroupId']]->getUser())
                                                        ->SetYandexLogin($AdGroups[$phrase['AdGroupId']]->getYandexLogin())
                                                        ->SetCampain($AdGroups[$phrase['AdGroupId']]->getCampain())
                                                        ->SetAdGroup($AdGroups[$phrase['AdGroupId']])
                                                        ->SetStrategy($AdGroups[$phrase['AdGroupId']]->getStrategy())
                                                        ->setMaxPrice($AdGroups[$phrase['AdGroupId']]->getMaxPrice());

                                                    $newPhrasesIDs[] = $phrase['Id'];
                                                    $Phrases[$phrase['Id']] = $NewPhrase;
                                                    $EM->persist($NewPhrase);

                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }


                //Добавляем для новых фраз ставки
                //Добавляем для них значение ставок
                if ($newPhrasesIDs) {
                    $request_data = ['SelectionCriteria' => [
                        'KeywordIds' => $newPhrasesIDs],
                        'FieldNames' => ['KeywordId', 'SearchPrices', 'MinSearchPrice', 'CurrentSearchPrice']
                    ];
                    $bidsPacks = $YandexApi5->send5LimitedRequest('bids', 'get', $request_data, [], 'Bids');

                    //Идем по пакам ставок
                    if (!$YandexApi5->hasApiError() && !$bidsPacks['errors'] && count($bidsPacks['data'])) {
                        foreach ($bidsPacks['data'] as $bidsPack) {
                            if (count($bidsPack['result'])) {
                                foreach ($bidsPack['result'] as $bid) {

                                    foreach ($bid['SearchPrices'] as $searchPrice) {
                                        if ($searchPrice['Position'] == 'PREMIUMFIRST') {
                                            $Phrases[$bid['KeywordId']]->setPremiumMax($searchPrice['Price'] / 1000000);
                                        } else if ($searchPrice['Position'] == 'PREMIUMBLOCK') {
                                            $Phrases[$bid['KeywordId']]->setPremiumMin($searchPrice['Price'] / 1000000);
                                        } else if ($searchPrice['Position'] == 'FOOTERFIRST') {
                                            $Phrases[$bid['KeywordId']]->setMax($searchPrice['Price'] / 1000000);
                                        } else if ($searchPrice['Position'] == 'FOOTERBLOCK') {
                                            $Phrases[$bid['KeywordId']]->setMin($searchPrice['Price'] / 1000000);
                                        }
                                    }

                                    $Phrases[$bid['KeywordId']]->setCurrentOnSearch($bid['CurrentSearchPrice'] / 1000000);
                                    $Phrases[$bid['KeywordId']]->setMinPrice($bid['MinSearchPrice'] / 1000000);

                                }
                            }
                        }
                    }
                }

                //Удаляем фразы
                if ($actualPhrasesIDs) {
                    foreach ($Phrases as $Phrase) {
                        if (array_search($Phrase->getPhraseID(), $actualPhrasesIDs) === false) {
                            $EM->remove($Phrase);
                        }
                    }
                }

            }


            //Для всех групп обновляем отперту времени последней проверки изменение
            //Отметка мимнимальная
            if ($newCheckPhrasesTimeStamp) {
                foreach ($AdGroups as $AdGroup) {
                    $AdGroup->setCheckPhrasesTimeStamp($newCheckPhrasesTimeStamp);
                }
            }

            $EM->flush();

            foreach ($adGroupsIDs as $yandexLoginId => $yandexLoginAdGroupsIDs) {
                $EM->getRepository('BroAppBundle:AdGroup')->updateAdGroupStatus('actual', $yandexLoginAdGroupsIDs);
                $EM->getRepository('BroAppBundle:AdGroup')->updateAdGroupLastUpdate(new \DateTime('now'), $yandexLoginAdGroupsIDs);

                //Выдает ебанутую ошибку
               /* if($newCheckPhrasesTimeStamp){
                    $EM->getRepository('BroAppBundle:AdGroup')->updateAdGroupStatus($newCheckPhrasesTimeStamp, $yandexLoginAdGroupsIDs, 'сheckPhrasesTimeStamp');
                }*/

            }



        }



        //возможны проблемы с большим расходом памяти
        //Поэтому стоит ограничитель редиректов по счетчику
        if($step<$max_steps){
            return $this->redirectToRoute('update_phrases_data', ['step'=>$step+1]);
        } else if ($step>=$max_steps){
            $Logger->info('Сработал ограничитель при загрузке фраз', ['step'=>$step]);
        }

        $end_time=(microtime(true) - $start);
        $end_memory=memory_get_usage();


        $Logger->info('Шаг по импорту фраз с яндекса. Время выполнения: '.round($end_time, 2).' сек. Съеденая память: '.($end_memory-$start_memory).' Б', []);
        return $this->render('BroAppBundle:StaticElements:test.html.twig', array('time'=>$end_time, 'memory'=>$end_memory-$start_memory));

    }


    public function updatePhrasesBidsAction($step=1){

        $Request=$this->getRequest();
        $EM = $this->getDoctrine()->getManager();
        $YandexApi5 = $this->get('yandex_api5');
        $Logger = $this->get('monolog.logger.update');

        set_time_limit(0);

        $start = microtime(true);
        $start_memory=memory_get_usage();
        $end_memory=$start_memory;

        //Эти параметры можно варьировать в зависимости от сервера
        $max_steps=$this->container->getParameter('load_phrases_n_steps');
        //$max_steps=1;
        $maxAdGroups=$this->container->getParameter('load_phrases_n_items');
        $updatePauseInterval='-'.$this->container->getParameter('load_bids_pause').' minutes';
        //$updatePauseInterval='-0 minutes';
        //$maxAdGroups=10;


        $AdGroups=$EM->getRepository('BroAppBundle:AdGroup')->findWithFilter(['working', 'bids_active'], $maxAdGroups, false, $updatePauseInterval);
        //$AdGroups=$EM->getRepository('BroAppBundle:AdGroup')->findById(274160);
        //$AdGroups['266499532']=$EM->getRepository('BroAppBundle:AdGroup')->findOneById(274161);

        if(!count($AdGroups)){
            $EM->getRepository('BroAppBundle:AdGroup')->activateAdGroup('bidsStatus');
            $AdGroups=$EM->getRepository('BroAppBundle:AdGroup')->findWithFilter(['working', 'bids_active'], $maxAdGroups, false, $updatePauseInterval);
        }
        //dump($AdGroups);

        if (count($AdGroups)) {
            $adGroupsIDs = [];

            //Помечаем что объявления обновляются
            //так как процесс долгий и что бы не было наложений
            foreach ($AdGroups as $key=>$AdGroup) {
                //$AdGroup->setDataStatus('upload');
                $adGroupsIDs[$AdGroup->getYandexLogin()->getId()][] = $AdGroup->getAdGroupID();
                $Logins[$AdGroup->getYandexLogin()->getId()] = $AdGroup->getYandexLogin();
            }

            foreach ($adGroupsIDs as $yandexLoginId => $yandexLoginAdGroupsIDs) {
                $EM->getRepository('BroAppBundle:AdGroup')->updateAdGroupStatus('upload', $yandexLoginAdGroupsIDs, 'bidsStatus');
            }


            foreach ($adGroupsIDs as $yandexLoginId => $yandexLoginAdGroupsIDs) {

                $YandexApi5->setToken5($Logins[$yandexLoginId]->getToken(), $Logins[$yandexLoginId]);
                $Phrases = $EM->getRepository('BroAppBundle:Phrase')->findByAdGroupsIDs($yandexLoginAdGroupsIDs);

                $request_data=[
                    'SelectionCriteria'=>[
                        'AdGroupIds'=>$yandexLoginAdGroupsIDs,
                    ],
                    'FieldNames'=>['KeywordId', 'SearchPrices', 'MinSearchPrice', 'CurrentSearchPrice']
                ];

                $bidsPacks=$YandexApi5->send5LimitedRequest('bids', 'get', $request_data, [], 'Bids');

                //Идем по пакам ставок
                if (!$YandexApi5->hasApiError() && !$bidsPacks['errors'] && count($bidsPacks['data'])) {
                    foreach ($bidsPacks['data'] as $bidsPack) {
                        if (count($bidsPack['result'])) {
                            foreach ($bidsPack['result'] as $bid) {


                                if(isset($Phrases[$bid['KeywordId']])){

                                    if (!$this->get('bro.compare')->compareBids($bid, $Phrases[$bid['KeywordId']])) {

                                        foreach($bid['SearchPrices'] as $searchPrice){
                                            if($searchPrice['Position']=='PREMIUMFIRST'){
                                                $Phrases[$bid['KeywordId']]->setPremiumMax($searchPrice['Price']/1000000);
                                            } else if($searchPrice['Position']=='PREMIUMBLOCK'){
                                                $Phrases[$bid['KeywordId']]->setPremiumMin($searchPrice['Price']/1000000);
                                            } else if($searchPrice['Position']=='FOOTERFIRST'){
                                                $Phrases[$bid['KeywordId']]->setMax($searchPrice['Price']/1000000);
                                            } else if($searchPrice['Position']=='FOOTERBLOCK'){
                                                $Phrases[$bid['KeywordId']]->setMin($searchPrice['Price']/1000000);
                                            }
                                        }

                                        $Phrases[$bid['KeywordId']]->setCurrentOnSearch($bid['CurrentSearchPrice']/1000000);
                                        $Phrases[$bid['KeywordId']]->setMinPrice($bid['MinSearchPrice']/1000000);
                                    }



                                }

                            }
                        }
                    }
                }

            }


            $EM->flush();

            foreach ($adGroupsIDs as $yandexLoginId => $yandexLoginAdGroupsIDs) {
                $EM->getRepository('BroAppBundle:AdGroup')->updateAdGroupStatus('actual', $yandexLoginAdGroupsIDs, 'bidsStatus');
                $EM->getRepository('BroAppBundle:AdGroup')->updateAdGroupLastUpdate(new \DateTime('now'), $yandexLoginAdGroupsIDs, 'bidsLastUpdate');
            }


        }



        //возможны проблемы с большим расходом памяти
        //Поэтому стоит ограничитель редиректов по счетчику
        if($step<$max_steps){
            return $this->redirectToRoute('update_phrases_bids', ['step'=>$step+1]);
        } else if ($step>=$max_steps){
            $Logger->info('Сработал ограничитель при загрузке ставок', ['step'=>$step]);
        }

        $end_time=(microtime(true) - $start);
        $end_memory=memory_get_usage();


        $Logger->info('Шаг по импорту ставок с яндекса. Время выполнения: '.round($end_time, 2).' сек. Съеденая память: '.($end_memory-$start_memory).' Б', []);
        return $this->render('BroAppBundle:StaticElements:test.html.twig', array('time'=>$end_time, 'memory'=>$end_memory-$start_memory));

    }



    //НЕ ИСПОЛЬЗУЕТСЯ
    public function loadPhrasesDataAction($step=1){

        $Request=$this->getRequest();
        $EM = $this->getDoctrine()->getManager();
        $YandexApi = $this->get('yandex_api');
        $Logger = $this->get('monolog.logger.update');

        set_time_limit(0);

        $start = microtime(true);
        $start_memory=memory_get_usage();
        $end_memory=$start_memory;

        //Эти параметры можно варьировать в зависимости от сервера
        $max_steps=$this->container->getParameter('load_phrases_n_steps');
        //$max_steps=1;
        $maxBanners=$this->container->getParameter('load_phrases_n_items');
        $updatePauseInterval='-'.$this->container->getParameter('load_phrases_pause').' minutes';
        //$maxBanners=10000000000;

        $Banners=$EM->getRepository('BroAppBundle:Banner')->findWithFilter(['working', 'data_active'], $maxBanners, $updatePauseInterval);
        $NewPhrase = new Phrase();

        //dump($Banners);


        if(!count($Banners)){
            $EM->getRepository('BroAppBundle:Banner')->activateBanners();
            $Banners=$EM->getRepository('BroAppBundle:Banner')->findWithFilter(['working', 'data_active'], $maxBanners, $updatePauseInterval);
        }


        if (count($Banners)) {
            $bannersIDs=[];
            $tokens=[];
            $logins=[];

            //Помечаем что объявления обновляются
            //так как процесс долгий и что бы не было наложений
            foreach ($Banners as $Banner) {
                $Banner->setDataStatus('upload');
                $bannersIDs[$Banner->getYandexLogin()->getId()][]=$Banner->getBannerID();
                $tokens[$Banner->getYandexLogin()->getId()]=$Banner->getYandexLogin()->getToken();
                $logins[$Banner->getYandexLogin()->getId()]=$Banner->getYandexLogin()->getLogin();
            }
            $EM->flush();

//dump($bannersIDs);

            foreach($bannersIDs as $yandexLoginId=>$yandexLoginBannersIDs){

                $YandexApi->setToken($tokens[$yandexLoginId]);
                $NewPhraseYandexLogin=$EM->getRepository('BroAppBundle:YandexLogin')->findOneById($yandexLoginId);
                $NewPhraseUser=$NewPhraseYandexLogin->getUser();


                //Загружаем фразы
                //предположительно такой метод будет быстрее чем загрухка сразу GetBanners WithPrices
                //потому что прийдется загружать данные дважды
                //но если измененных объявлений мало, то фактически один раз
                $phrases = $EM->getRepository('BroAppBundle:Phrase')->findArrayByBannesrIDs($yandexLoginBannersIDs);
                //dump($phrases);
                $phrases_packs = $YandexApi->sendLimitedRequest('GetBanners', ['BannerIDS' => $yandexLoginBannersIDs, 'GetPhrases' => 'WithPrices', 'FieldsNames' => ['BannerID', 'CampaignID']], 'data');

                $new_phrases = [];
                $phrases_ids_for_delete = [];
                //dump($phrases_packs);
                //Вспомогательный массив для поиска удаленных
                $phrasesIDs = [];
                $editPhrasesIDs = [];

                //dump($phrases_packs);
                //  exit;


                if (!$YandexApi->hasApiError() && !$phrases_packs['errors'] && is_array($phrases_packs['data'])&&count($phrases_packs['data'])) {

                    foreach ($phrases_packs['data'] as $phrases_pack) {
                        foreach ($phrases_pack['result'] as $banner) {
                            if (count($banner['Phrases'])) {

                                //Идем по фразам от яндекса
                                foreach ($banner['Phrases'] as $phrase) {

                                    //Проверяем обрабатывалась ли уэе эта фраза
                                    //так сделано потому что для групп объявлений они дублируются
                                    //если эти дубли важны то прийдется убрать это
                                    //но тогда не забыть удалить unset($phrases[(string) $phrase['PhraseID']]);
                                    //потому что при этом дубликаты фраз помечаются как отсутствующие в базе
                                    if (!isset($phrasesIDs[(string)$phrase['PhraseID']])) {

                                        //Сохраняем айдишники фраз для поиска удаленных
                                        //можно было бы поместить в массив с последовательными ключами
                                        //но так как для групп объявлений фразы дублируются,
                                        //так по идее будет экономится память + иссет быстрее
                                        $phrasesIDs[(string)$phrase['PhraseID']] = true;

                                        //Если фраза есть в бд
                                        if (isset($phrases[(string)$phrase['PhraseID']])) {

                                            //Опасно конечно сравнивать
                                            //но это сэкономит кучу ресурсов, поэтому рескнем
                                            //но в случае непонятных проблем с данными сюда смотреть в первую очередь

                                            //если данные фразы изменились - сохраняем их
                                            if (!$this->get('bro.compare')->compareArrays($phrases[(string)$phrase['PhraseID']][0], $phrase)) {

                                                $editPhrasesIDs[] = $phrase['PhraseID'];

                                                //Идем по доблирующимся фразам для групп объявлений
                                                foreach ($phrases[(string)$phrase['PhraseID']] as &$old_phrase) {
                                                    //Идем по дынным фраз
                                                    foreach ($old_phrase as $key => &$old_phrase_value) {
                                                        //если такой элемент есть в данных от яндекса - меняем
                                                        if (isset($phrase[$key])) {
                                                            $old_phrase_value = $phrase[$key];
                                                        }
                                                    }
                                                  unset($old_phrase_value);
                                                }
                                                unset($old_phrase);

                                            //Если нет удаляем из списка
                                            } else {
                                                unset($phrases[(string)$phrase['PhraseID']]);
                                            }

                                            //Если нет добавляем
                                            //не уверен на самом деле что это экономичней чем
                                            //просто создать сущьность и зафлушить
                                        } else {
                                            $new_phrase = [];

                                            if (is_array($phrase)) {
                                                foreach ($phrase as $key => $phrase_data_value) {
                                                    $setter = 'Set' . $key;

                                                    //Отталкиваемся от свойств прописанных в модели фразы
                                                    if (property_exists($NewPhrase, $key) && method_exists($NewPhrase, $setter)
                                                    && ((property_exists($NewPhrase, 'noFill') && array_search($key, $NewPhrase->noFill) === false) || !property_exists($NewPhrase, 'noFill'))
                                                    ) {
                                                        if (!is_null($phrase_data_value)) {

                                                            //на всякий случай для массивов
                                                            if (is_array($phrase_data_value)) {
                                                                $phrase_data_value = json_encode($phrase_data_value);
                                                            }

                                                            $new_phrase[$key] = $phrase_data_value;

                                                        } else {
                                                            $new_phrase[$key] = '';
                                                        }
                                                    }

                                                }

                                                //Ставим дополнительные параметры
                                                $new_phrase['AutoStopped'] = false;

                                                //Привязывыем наследные параметры
                                                //и связаные сущьности


                                                $NewPhraseCampain=$EM->getRepository('BroAppBundle:Campain')->findOneBy(['CampaignID'=>$phrase['CampaignID']]);
                                                $NewPhraseAdGroup=$EM->getRepository('BroAppBundle:AdGroup')->findOneBy(['AdGroupID'=>$phrase['AdGroupID']]);

                                                if ($NewPhraseUser && $NewPhraseCampain&&$NewPhraseAdGroup) {


                                                    $new_phrase['user_id'] = $NewPhraseUser->getId();
                                                    $new_phrase['yandex_login_id'] = $yandexLoginId;
                                                    $new_phrase['campain_id'] = $NewPhraseCampain->getId();
                                                    $new_phrase['ad_group_id'] = $NewPhraseAdGroup->getId();

                                                    //ПОТОМ УБРАТЬ
                                                    //$new_phrase['banner_id']=$Campains[$phrase['CampaignID']]->getAdGroups()[(string) $phrase['AdGroupID']]->getBanners()[$phrase['BannerID']]->getId();

                                                    $new_phrase['strategy_id'] = $NewPhraseAdGroup->getStrategy()->getId();
                                                    $new_phrase['MaxPrice'] = $NewPhraseAdGroup->getMaxPrice();

                                                    $new_phrases[] = $new_phrase;

                                                } else {
                                                //$Logger->error('Почему то не найдено объявление для новой фразы', $phrase);
                                                }

                                            }
                                        }

                                    }
                                }
                            }
                        }
                    }







                    //Возможно стоит вынести этот юлок в отдельный иф
                    //где проверяется только ошибки апи

                    //Ищем удаленные
                    if (count($phrases)) {
                        foreach ($phrases as $phrase_id => $phrase) {
                            if (!isset($phrasesIDs[$phrase_id])) {
                                foreach ($phrase as $phrase_copy) {
                                    $phrases_ids_for_delete[] = $phrase_copy['id'];
                                }
                                unset($phrases[(string)$phrase_id]);
                            }
                        }
                    }


                    //Сохраняем идшники новых
                    $newPhrasesIDs = [];
                    foreach ($new_phrases as $phrase) {
                        $newPhrasesIDs[] = $phrase['PhraseID'];
                    }

                    //Работаем с базой напрямую
                    $EM->getRepository('BroAppBundle:Phrase')->addPhrases($new_phrases);
                    $EM->getRepository('BroAppBundle:Phrase')->editPhrases($phrases);

                    //ВРЕМЕННО. До установления причин наебалова с паразиным удалением
                    $EM->getRepository('BroAppBundle:Phrase')->deletePhrases($phrases_ids_for_delete);


                    if (count($newPhrasesIDs)) {
                        $Logger->info('Добавление фраз для логина ' . $logins[$yandexLoginId], $newPhrasesIDs);
                    }
                    if (count($editPhrasesIDs)) {
                        $Logger->info('Редактирование фраз для логина ' . $logins[$yandexLoginId], $editPhrasesIDs);
                    }
                    if (count($phrases_ids_for_delete)) {
                        $Logger->info('Удаление фраз для логина ' . $logins[$yandexLoginId], $phrases_ids_for_delete, ['$phrases' => $phrases, '$phrasesIDs' => $phrasesIDs]);
                    }

                }

            }


            foreach ($Banners as $Banner) {
                $Banner->setDataStatus('actual');
                $Banner->setLastUpdate(new \DateTime('now'));
            }
            $EM->flush();



            //возможны проблемы с большим расходом памяти
            //Поэтому стоит ограничитель редиректов по счетчику
            if($step<$max_steps){
                return $this->redirectToRoute('load_phrases_data', ['step'=>$step+1]);
            } else if ($step>=$max_steps){
                $Logger->info('Сработал ограничитель при загрузке фраз', ['step'=>$step]);
            }


        }

        //dump($Banners);


        $end_time=(microtime(true) - $start);
        $end_memory=memory_get_usage();

        $Logger->info('Шаг по импорту фраз с яндекса. Время выполнения: '.round($end_time, 2).' сек. Съеденая память: '.($end_memory-$start_memory).' Б', []);





        return $this->render('BroAppBundle:StaticElements:test.html.twig', array('time'=>$end_time, 'memory'=>$end_memory-$start_memory));
    }


	//Автономная загрузка кампании
	function loadYandexLoginCampainAction($campain_id, $yandex_login){

		$Request=$this->getRequest();
		$EM = $this->getDoctrine()->getManager();
		$YandexApi = $this->get('yandex_api');

		$StdStartegy=$EM->getRepository('BroAppBundle:Strategy')->findFirstOne();
		$Campain=$EM->getRepository('BroAppBundle:Campain')->findOneByIdWithYandexLogin($campain_id);
		$Campains=array();

		if($Campain){
			$YandexLogin=$Campain->getYandexLogin();
			$Campains[$Campain->getCampaignID()]=$Campain;

			$YandexApi->setToken($YandexLogin->getToken());
			$campains_list=$YandexApi->sendRequest('GetCampaignsParams', array('CampaignIDS'=>array($Campain->getCampaignID())), 'data');

			if(!empty($campains_list)){

				//КОСТЫЛЬ
				//При выполнении fill эта херня будет конфликтовать с внутренней стратегией
				//А так так заполнять в ручную не хочется, пока так
				unset($campains_list[0]['Strategy']);

				$Campain->fill($campains_list[0])
								->SetStartDate(new \DateTime($campains_list[0]['StartDate']));

				$this->loadBanners($EM, $Campains, array(array($Campain->getCampaignID())), $YandexApi);

				$this->AjaxResponse->setStatus('ok');

			} else {
				$this->AjaxResponse->addError($YandexApi->getError()['key'], $YandexApi->getError()['text']);
			}



		} else {
			$this->AjaxResponse->addError('m_0045', 'Кампания не найдена');
		}

		$EM->flush();

		if($Request->isXmlHttpRequest()){

			if(!$this->AjaxResponse->getHasErrors()){
    		$this->AjaxResponse->setData(array('workflow'=>$this->forwardBack()->getContent()), 'html', 'ok');
			}

			return $this->AjaxResponse->getResponse();
		}

		return new Response();
	}


  //**************************************************
  //******************** ДЕЙСТВИЯ ********************
  //**************************************************


  //**************************************************
  //****************** API ДЕЙСТВИЯ ******************
  //**************************************************





  public function massAction($action){
    $Request=$this->getRequest();
    //$referer_params = $this->get('router')->match(str_replace('http://'.$_SERVER['HTTP_HOST'], '', $_SERVER['HTTP_REFERER']));
    $result=false;

    switch ($action){
       case 'edit_strategy':

        if(count($Request->request->get('campains_ids'))>0){
          $this->forward('BroAppBundle:Manage/Campains:editCampainStrategy', array('campain_id'=> $Request->request->get('campains_ids'),
                                                                                   'strategy_id'=> $Request->request->get('strategy_id'),
                                                                                   'filter'=>(isset($referer_params['filter'])?$referer_params['filter']:false),
                                                                                   'ad_group_id'=>(isset($referer_params['ad_group_id'])?$referer_params['ad_group_id']:false),
                                                                                   'render_response'=>false
                                                                                ));
        }
        if(count($Request->request->get('ad_groups_ids'))>0){
          $this->forward('BroAppBundle:Manage/AdGroups:editAdGroupStrategy', array('ad_group_id'  => $Request->request->get('ad_groups_ids'),
                                                                                   'strategy_id' => $Request->request->get('strategy_id'),
                                                                                   'render_response'=>false
                                                                                  ));

        }
        if(count($Request->request->get('phrases_ids'))>0){
          $this->forward('BroAppBundle:Manage/Phrases:editPhraseStrategy', array('phrase_id'  => $Request->request->get('phrases_ids'),
                                                                                'strategy_id' => $Request->request->get('strategy_id'),
                                                                                'render_response'=>false
                                                                                ));

        }
       break;

       case 'edit_max_price':
        if(count($Request->request->get('campains_ids'))>0){
          $this->forward('BroAppBundle:Manage/Campains:editCampainMaxPrice', array('campain_id'=> $Request->request->get('campains_ids'),
                                                                                   'max_price'=> $Request->request->get('max_price'),
                                                                                   'filter'=>(isset($referer_params['filter'])?$referer_params['filter']:false),
                                                                                   'ad_group_id'=>(isset($referer_params['ad_group_id'])?$referer_params['ad_group_id']:false),
                                                                                   'render_response'=>false
                                                                                   ));
        }
        if(count($Request->request->get('ad_groups_ids'))>0){
          $this->forward('BroAppBundle:Manage/AdGroups:editAdGroupMaxPrice', array('ad_group_id'  => $Request->request->get('ad_groups_ids'),
                                                                                   'strategy_id' => $Request->request->get('strategy_id'),
                                                                                   'render_response'=>false
                                                                                  ));
        }
        if(count($Request->request->get('phrases_ids'))>0){
          $this->forward('BroAppBundle:Manage/Phrases:editPhraseMaxPrice', array('phrase_id'  => $Request->request->get('phrases_ids'),
                                                                                 'max_price' => $Request->request->get('max_price'),
                                                                                 'render_response'=>false
                                                                                ));
        }
       break;

       case 'Resume':
       case 'Stop':
       case 'Archive':
       case 'UnArchive':
         if(count($Request->request->get('ad_groups_ids'))>0){
            $this->forward('BroAppBundle:Manage/AdGroups:controllAdGroup', array('ad_group_id'=> $Request->request->get('ad_groups_ids'),
                                                                                'action'=> $action.'Banners',
                                                                                'render_response'=>false
                                                                                ));
         }

         if(count($Request->request->get('campains_ids'))>0){
            $this->forward('BroAppBundle:Manage/Campains:controllCampain', array('campain_id'=> $Request->request->get('campains_ids'),
                                                                                 'action'=> $action.'Campaign',
                                                                                 'filter'=>(isset($referer_params['filter'])?$referer_params['filter']:false),
                                                                                 'ad_group_id'=>(isset($referer_params['ad_group_id'])?$referer_params['ad_group_id']:false),
                                                                                 'render_response'=>false
                                                                                ));
         }

         if(count($Request->request->get('phrases_ids'))>0){
            $this->forward('BroAppBundle:Manage/Phrases:controllPhrase', array('phrase_id'=> $Request->request->get('phrases_ids'),
                                                                               'action'=> $action,
                                                                               'render_response'=>false
                                                                               ));
         }
       break;

       default: break;
    }

    if(!$this->AjaxResponse->getHasErrors()){

      if($Request->request->get('sub_action')){
        if($Request->request->get('sub_action')=='refresh'){
          $this->AjaxResponse->setData(array('workflow'=>$this->forwardBack()->getContent()), 'html', 'ok');
        } else if($Request->request->get('sub_action')=='refresh_content'){
          $this->AjaxResponse->setData(array('content'=>$this->forwardBack(true, true)->getContent()), 'html', 'ok');
        }
      } else {
        $this->AjaxResponse->setData(array('workflow'=>$this->forwardBack()->getContent()), 'html', 'ok');
      }

    }

    return $this->AjaxResponse->getResponse();
  }





  //**************************************************
  //******************** СТАТИКА *********************
  //**************************************************

	//Рендерим рабочее поле с мемню кампаний и соответствующим контентом
	public function renderManageSidebarAction($YandexLogins, $yandex_login=false, $yandex_client=false, $campain_id=false){


    $type='root';
    if($yandex_login){
      $type='login';
    }
    if($yandex_client) {
      $type='client';
    }

    return $this->render('BroAppBundle:Manage:sidebar.html.twig', array('type'=>$type,
                                                                        'YandexLogins'=>$YandexLogins,
                                                                        'yandex_login'=>$yandex_login,
                                                                        'yandex_client'=>$yandex_client,
                                                                        'campain_id'=>$campain_id
                                                                       )
                        );
	}

  //Рендерим хлебные крошки
  public function renderBreadCrumbsAction($yandex_login=false, $yandex_client=false, $campain_id=false){
    $EM = $this->getDoctrine()->getManager();

    $menu=array(array('route'=>'manage','name'=>'Управление'));

    if($yandex_login){
      $menu[]=array('route'=>'manage_yandex_login', 'route_data'=>array('yandex_login'=>$yandex_login), 'name'=>$yandex_login);
    }
    if($yandex_login&&$yandex_client){
      $menu[]=array('route'=>'manage_yandex_client', 'route_data'=>array('yandex_parent_login'=>$yandex_login, 'yandex_login'=>$yandex_client), 'name'=>$yandex_client);
    }

    if($yandex_login&&!$yandex_client&&$campain_id){

      $Campain=$EM->getRepository('BroAppBundle:Campain')->findOneById($campain_id);
      $menu[]=array('route'=>'manage_campain', 'route_data'=>array('yandex_login'=>$yandex_login, 'campain_id'=>$campain_id), 'name'=>$Campain->getName());
    }

    if($yandex_login&&$yandex_client&&$campain_id){
      //$Campain=$YandexLogins[$yandex_login]->getSubLogins()[$yandex_client]->getCampains();
      $Campain=$EM->getRepository('BroAppBundle:Campain')->findOneById($campain_id);

      $menu[]=array('route'=>'manage_client_campain', 'route_data'=>array('yandex_login'=>$yandex_login, 'yandex_client'=>$yandex_client, 'campain_id'=>$campain_id), 'name'=>$Campain->getName());
    }

    return $this->forward('BroAppBundle:StaticElements:echoMenu', array('menu'=>$menu, 'menu_class'=>'breadcrumbs'));
  }

  //Рендерим фильтр объявлений
  public function renderAdGroupsFilterAction($yandex_login=false, $yandex_client=false, $campain_id=false, $filter='active', $ad_group_id=false){
    $menu=array();
    $route='manage_campain';
    $route_data=array('yandex_login'=>$yandex_login, 'campain_id'=>$campain_id);
    $EM = $this->getDoctrine()->getManager();

    if($yandex_client){
      $route='manage_client_campain';
      $route_data['yandex_client']=$yandex_client;
    }

    $filters=array(array('name'=>'Все', 'filter'=>'all'),
                   array('name'=>'Активные', 'filter'=>'active'),
                   array('name'=>'На модерации', 'filter'=>'moderate'),
                   array('name'=>'Отклоненные', 'filter'=>'rejected'),
                   array('name'=>'Остановленные', 'filter'=>'stopped'),
                   array('name'=>'В архиве', 'filter'=>'archived'),
                   array('name'=>'Черновики', 'filter'=>'draft')
                  );


    foreach($filters as $filter_item){
      $route_data['filter']=$filter_item['filter'];
      $route_data['ad_group_id']=0;
      $menu_data=array('route'=>$route, 'name'=>$filter_item['name']);

      if($filter_item['filter']==$filter){
        $route_data['ad_group_id']=$ad_group_id;
        $menu_data['class']='active';
        $menu_data['active']=true;
        $menu_data['href']='javascript:void(0);';
        $menu_data['filter']=$filter_item['filter'];

        $menu_data['AdGroups']=$EM->getRepository('BroAppBundle:AdGroup')->findByCampainId($campain_id, $filter,true, false);
        //dump($menu_data['AdGroups']);
        if(count($menu_data['AdGroups'])>1){
          $menu_data['link_class']='button dropdown';
          $menu_data['data']=array('dropdown'=>'dropBanners', 'options'=>'is_hover:true');
        }
      }

      $menu_data['route_data']=$route_data;

      $menu[]=$menu_data;
    }


    return $this->forward('BroAppBundle:StaticElements:echoMenu', array('menu'=>$menu, 'menu_class'=>'sub-nav filtred_banners', 'template'=>'BroAppBundle:Manage:ad_group_filter_menu.html.twig', 'activate_type'=>'full'));
  }

  //Рендерим фильтр объявлений
  public function renderCampainsFilterAction($yandex_login=false, $yandex_client=false, $campains_filter='active', $campain_id=false){
    $menu=array();


    $EM = $this->getDoctrine()->getManager();

    if(!$yandex_client){
      $route='manage_yandex_login';
      $campains_yandex_login=$yandex_login;
      $route_data=array('yandex_login'=>$campains_yandex_login);

    } else {
      $route='manage_yandex_client';
      $campains_yandex_login=$yandex_client;
      $route_data=array('yandex_parent_login'=>$yandex_login, 'yandex_login'=>$campains_yandex_login);
    }

    //Обязательно прописывать их и правилах роутинга
    $campains_filters=array(array('name'=>'Все', 'filter'=>'all'),
                            array('name'=>'Активные', 'filter'=>'active'),
                            array('name'=>'Остановленные', 'filter'=>'stopped'),
                            array('name'=>'В архиве', 'filter'=>'archived')
                           );


    foreach($campains_filters as $filter){
      $route_data['campains_filter']=$filter['filter'];
      $route_data['campain_id']=0;
      $menu_data=array('route'=>$route, 'name'=>$filter['name']);

      if($filter['filter']==$campains_filter){
        $route_data['campain_id']=$campain_id;
        $menu_data['class']='active';
        $menu_data['active']=true;
        $menu_data['href']='javascript:void(0);';
        $menu_data['campains_filter']=$filter['filter'];

        $menu_data['Campains']=$EM->getRepository('BroAppBundle:Campain')->findByYandexLogin($campains_yandex_login, $campains_filter);

        if(count($menu_data['Campains'])>1){
          $menu_data['link_class']='button dropdown';
          $menu_data['data']=array('dropdown'=>'dropCampains', 'options'=>'is_hover:true');

          unset($Campain);
        }
      }

      $menu_data['route_data']=$route_data;

      $menu[]=$menu_data;
    }

    return $this->forward('BroAppBundle:StaticElements:echoMenu', array('menu'=>$menu, 'menu_class'=>'sub-nav filtred_campains', 'template'=>'BroAppBundle:Manage:campain_filter_menu.html.twig', 'activate_type'=>'full'));
  }



  //Рендерим фильтр объявлений
  public function renderClientsFilterAction($yandex_login=false, $filter='active'){
    $EM = $this->getDoctrine()->getManager();

    $menu=array();
    $route='manage_yandex_login';
    $route_data=array('yandex_login'=>$yandex_login, 'filter'=>$filter);
    $filters=array(array('name'=>'Все', 'filter'=>'all'),
                   array('name'=>'Активные', 'filter'=>'active'),
                   array('name'=>'В архиве', 'filter'=>'archived')
                  );


    foreach($filters as $filter_item){
      $route_data['filter']=$filter_item['filter'];
      $menu_data=array('route'=>$route, 'name'=>$filter_item['name']);

      if($filter_item['filter']==$filter){
        $menu_data['class']='active';
        $menu_data['active']=true;
        $menu_data['href']='javascript:void(0);';
      }

      $menu_data['route_data']=$route_data;

      $menu[]=$menu_data;
    }

    return $this->forward('BroAppBundle:StaticElements:echoMenu', array('menu'=>$menu, 'menu_class'=>'sub-nav filtred_yandex_logins', 'activate_type'=>'full'));
  }

}
