<?php

/**
 * PHP Mikrotik Billing (https://ibnux.github.io/phpmixbill/)

 **/
_auth();
$ui->assign('_title', $_L['Voucher'] . '- ' . $config['CompanyName']);
$ui->assign('_system_menu', 'voucher');

$action = $routes['1'];
$user = User::_info();
$ui->assign('_user', $user);

use PEAR2\Net\RouterOS;

require_once 'system/autoload/PEAR2/Autoload.php';

switch ($action) {

    case 'activation':
        $ui->display('user-activation.tpl');
        break;

    case 'activation-post':
        $code = _post('code');

        $v1 = ORM::for_table('tbl_voucher')->where('code', $code)->where('status', 0)->find_one();

        $c = ORM::for_table('tbl_customers')->find_one($user['id']);
        $p = ORM::for_table('tbl_plans')->find_one($v1['id_plan']);
        $b = ORM::for_table('tbl_user_recharges')->where('customer_id', $user['id'])->find_one();

        $date_now = date("Y-m-d H:i:s");
        $date_only = date("Y-m-d");
        $time = date("H:i:s");

        $mikrotik = Router::_info($v1['routers']);
        if($p['validity_unit']=='Months'){
            $date_exp = date("Y-m-d", strtotime('+'.$p['validity'].' month'));
        }else if($p['validity_unit']=='Days'){
            $date_exp = date("Y-m-d", strtotime('+'.$p['validity'].' day'));
        }else if($p['validity_unit']=='Hrs'){
            $datetime = explode(' ',date("Y-m-d H:i:s", strtotime('+'.$p['validity'].' hour')));
            $date_exp = $datetime[0];
            $time = $datetime[1];
        }else if($p['validity_unit']=='Mins'){
            $datetime = explode(' ',date("Y-m-d H:i:s", strtotime('+'.$p['validity'].' minute')));
            $date_exp = $datetime[0];
            $time = $datetime[1];
        }
        if ($v1) {
            if ($v1['type'] == 'Hotspot') {
                if ($b) {
                    try {
                        $iport = explode(":", $mikrotik['ip_address']);
                        $client = new RouterOS\Client($iport[0], $mikrotik['username'], $mikrotik['password'], ($iport[1]) ? $iport[1] : null);
                    } catch (Exception $e) {
                        die("Unable to connect to the router.<br>".$e->getMessage());
                    }
                    $printRequest = new RouterOS\Request('/ip/hotspot/user/print');
                    $printRequest->setArgument('.proplist', '.id');
                    $printRequest->setQuery(RouterOS\Query::where('name', $c['username']));
                    $id = $client->sendSync($printRequest)->getProperty('.id');

                    $setRequest = new RouterOS\Request('/ip/hotspot/user/remove');
                    $setRequest->setArgument('numbers', $id);
                    $client->sendSync($setRequest);

                    /* iBNuX Added:
					* 	Time limit to Mikrotik
					*	'Time_Limit', 'Data_Limit', 'Both_Limit'
					*/
                    $addRequest = new RouterOS\Request('/ip/hotspot/user/add');
                    if ($p['typebp'] == "Limited") {
                        if ($p['limit_type'] == "Time_Limit") {
                            if ($p['time_unit'] == 'Hrs')
                                $timelimit = $p['time_limit'] . ":00:00";
                            else
                                $timelimit = "00:" . $p['time_limit'] . ":00";
                            $client->sendSync(
                                $addRequest
                                    ->setArgument('name', $c['username'])
                                    ->setArgument('profile', $p['name_plan'])
                                    ->setArgument('password', $c['password'])
                                    ->setArgument('limit-uptime', $timelimit)
                            );
                        } else if ($p['limit_type'] == "Data_Limit") {
                            if ($p['data_unit'] == 'GB')
                                $datalimit = $p['data_limit'] . "000000000";
                            else
                                $datalimit = $p['data_limit'] . "000000";
                            $client->sendSync(
                                $addRequest
                                    ->setArgument('name', $c['username'])
                                    ->setArgument('profile', $p['name_plan'])
                                    ->setArgument('password', $c['password'])
                                    ->setArgument('limit-bytes-total', $datalimit)
                            );
                        } else if ($p['limit_type'] == "Both_Limit") {
                            if ($p['time_unit'] == 'Hrs')
                                $timelimit = $p['time_limit'] . ":00:00";
                            else
                                $timelimit = "00:" . $p['time_limit'] . ":00";
                            if ($p['data_unit'] == 'GB')
                                $datalimit = $p['data_limit'] . "000000000";
                            else
                                $datalimit = $p['data_limit'] . "000000";
                            $client->sendSync(
                                $addRequest
                                    ->setArgument('name', $c['username'])
                                    ->setArgument('profile', $p['name_plan'])
                                    ->setArgument('password', $c['password'])
                                    ->setArgument('limit-uptime', $timelimit)
                                    ->setArgument('limit-bytes-total', $datalimit)
                            );
                        }
                    } else {
                        $client->sendSync(
                            $addRequest
                                ->setArgument('name', $c['username'])
                                ->setArgument('profile', $p['name_plan'])
                                ->setArgument('password', $c['password'])
                        );
                    }

                    $b->customer_id = $user['id'];
                    $b->username = $c['username'];
                    $b->plan_id = $v1['id_plan'];
                    $b->namebp = $p['name_plan'];
                    $b->recharged_on = $date_only;
                    $b->expiration = $date_exp;
                    $b->time = $time;
                    $b->status = "on";
                    $b->method = "voucher";
                    $b->routers = $v1['routers'];
                    $b->type = "Hotspot";
                    $b->save();

                    // insert table transactions
                    $t = ORM::for_table('tbl_transactions')->create();
                    $t->invoice = "INV-" . _raid(5);
                    $t->username = $c['username'];
                    $t->plan_name = $p['name_plan'];
                    $t->price = $p['price'];
                    $t->recharged_on = $date_only;
                    $t->expiration = $date_exp;
                    $t->time = $time;
                    $t->method = "voucher";
                    $t->routers = $v1['routers'];
                    $t->type = "Hotspot";
                    $t->save();
                } else {
                    try {
                        $iport = explode(":", $mikrotik['ip_address']);
                        $client = new RouterOS\Client($iport[0], $mikrotik['username'], $mikrotik['password'], ($iport[1]) ? $iport[1] : null);
                    } catch (Exception $e) {
                        die("Unable to connect to the router.<br>".$e->getMessage());
                    }
                    /* iBNuX Added:
					* 	Time limit to Mikrotik
					*	'Time_Limit', 'Data_Limit', 'Both_Limit'
					*/
                    $addRequest = new RouterOS\Request('/ip/hotspot/user/add');
                    if ($p['typebp'] == "Limited") {
                        if ($p['limit_type'] == "Time_Limit") {
                            if ($p['time_unit'] == 'Hrs')
                                $timelimit = $p['time_limit'] . ":00:00";
                            else
                                $timelimit = "00:" . $p['time_limit'] . ":00";
                            $client->sendSync(
                                $addRequest
                                    ->setArgument('name', $c['username'])
                                    ->setArgument('profile', $p['name_plan'])
                                    ->setArgument('password', $c['password'])
                                    ->setArgument('limit-uptime', $timelimit)
                            );
                        } else if ($p['limit_type'] == "Data_Limit") {
                            if ($p['data_unit'] == 'GB')
                                $datalimit = $p['data_limit'] . "000000000";
                            else
                                $datalimit = $p['data_limit'] . "000000";
                            $client->sendSync(
                                $addRequest
                                    ->setArgument('name', $c['username'])
                                    ->setArgument('profile', $p['name_plan'])
                                    ->setArgument('password', $c['password'])
                                    ->setArgument('limit-bytes-total', $datalimit)
                            );
                        } else if ($p['limit_type'] == "Both_Limit") {
                            if ($p['time_unit'] == 'Hrs')
                                $timelimit = $p['time_limit'] . ":00:00";
                            else
                                $timelimit = "00:" . $p['time_limit'] . ":00";
                            if ($p['data_unit'] == 'GB')
                                $datalimit = $p['data_limit'] . "000000000";
                            else
                                $datalimit = $p['data_limit'] . "000000";
                            $client->sendSync(
                                $addRequest
                                    ->setArgument('name', $c['username'])
                                    ->setArgument('profile', $p['name_plan'])
                                    ->setArgument('password', $c['password'])
                                    ->setArgument('limit-uptime', $timelimit)
                                    ->setArgument('limit-bytes-total', $datalimit)
                            );
                        }
                    } else {
                        $client->sendSync(
                            $addRequest
                                ->setArgument('name', $c['username'])
                                ->setArgument('profile', $p['name_plan'])
                                ->setArgument('password', $c['password'])
                        );
                    }

                    $d = ORM::for_table('tbl_user_recharges')->create();
                    $d->customer_id = $user['id'];
                    $d->username = $c['username'];
                    $d->plan_id = $v1['id_plan'];
                    $d->namebp = $p['name_plan'];
                    $d->recharged_on = $date_only;
                    $d->expiration = $date_exp;
                    $d->time = $time;
                    $d->status = "on";
                    $d->method = "voucher";
                    $d->routers = $v1['routers'];
                    $d->type = "Hotspot";
                    $d->save();

                    // insert table transactions
                    $t = ORM::for_table('tbl_transactions')->create();
                    $t->invoice = "INV-" . _raid(5);
                    $t->username = $c['username'];
                    $t->plan_name = $p['name_plan'];
                    $t->price = $p['price'];
                    $t->recharged_on = $date_only;
                    $t->expiration = $date_exp;
                    $t->time = $time;
                    $t->method = "voucher";
                    $t->routers = $v1['routers'];
                    $t->type = "Hotspot";
                    $t->save();
                }

                $v1->status = "1";
                $v1->user = $c['username'];
                $v1->save();
                // Telegram to Admin
                sendTelegram( $c['username']." Activate Voucher Hotspot\n".$p['name_plan'].
                "\nRouter: ".$v1['routers'].
                "\nPrice: ".$p['price']);
            } else {
                if ($b) {
                    try {
                        $iport = explode(":", $mikrotik['ip_address']);
                        $client = new RouterOS\Client($iport[0], $mikrotik['username'], $mikrotik['password'], ($iport[1]) ? $iport[1] : null);
                    } catch (Exception $e) {
                        die("Unable to connect to the router.<br>".$e->getMessage());
                    }
                    $printRequest = new RouterOS\Request('/ppp/secret/print');
                    $printRequest->setArgument('.proplist', '.id');
                    $printRequest->setQuery(RouterOS\Query::where('name', $c['username']));
                    $id = $client->sendSync($printRequest)->getProperty('.id');

                    $setRequest = new RouterOS\Request('/ppp/secret/remove');
                    $setRequest->setArgument('numbers', $id);
                    $client->sendSync($setRequest);

                    $addRequest = new RouterOS\Request('/ppp/secret/add');
                    $client->sendSync(
                        $addRequest
                            ->setArgument('name', $c['username'])
                            ->setArgument('service', 'pppoe')
                            ->setArgument('profile', $p['name_plan'])
                            ->setArgument('password', $c['password'])
                    );

                    $b->customer_id = $user['id'];
                    $b->username = $c['username'];
                    $b->plan_id = $v1['id_plan'];
                    $b->namebp = $p['name_plan'];
                    $b->recharged_on = $date_only;
                    $b->expiration = $date_exp;
                    $b->time = $time;
                    $b->status = "on";
                    $b->method = "voucher";
                    $b->routers = $v1['routers'];
                    $b->type = "PPPOE";
                    $b->save();

                    // insert table transactions
                    $t = ORM::for_table('tbl_transactions')->create();
                    $t->invoice = "INV-" . _raid(5);
                    $t->username = $c['username'];
                    $t->plan_name = $p['name_plan'];
                    $t->price = $p['price'];
                    $t->recharged_on = $date_only;
                    $t->expiration = $date_exp;
                    $t->time = $time;
                    $t->method = "voucher";
                    $t->routers = $v1['routers'];
                    $t->type = "PPPOE";
                    $t->save();
                } else {
                    try {
                        $iport = explode(":", $mikrotik['ip_address']);
                        $client = new RouterOS\Client($iport[0], $mikrotik['username'], $mikrotik['password'], ($iport[1]) ? $iport[1] : null);
                    } catch (Exception $e) {
                        die("Unable to connect to the router.<br>".$e->getMessage());
                    }
                    $addRequest = new RouterOS\Request('/ppp/secret/add');
                    $client->sendSync(
                        $addRequest
                            ->setArgument('name', $c['username'])
                            ->setArgument('service', 'pppoe')
                            ->setArgument('profile', $p['name_plan'])
                            ->setArgument('password', $c['password'])
                    );

                    $d = ORM::for_table('tbl_user_recharges')->create();
                    $d->customer_id = $user['id'];
                    $d->username = $c['username'];
                    $d->plan_id = $v1['id_plan'];
                    $d->namebp = $p['name_plan'];
                    $d->recharged_on = $date_only;
                    $d->expiration = $date_exp;
                    $d->time = $time;
                    $d->status = "on";
                    $d->method = "voucher";
                    $d->routers = $v1['routers'];
                    $d->type = "PPPOE";
                    $d->save();

                    // insert table transactions
                    $t = ORM::for_table('tbl_transactions')->create();
                    $t->invoice = "INV-" . _raid(5);
                    $t->username = $c['username'];
                    $t->plan_name = $p['name_plan'];
                    $t->price = $p['price'];
                    $t->recharged_on = $date_only;
                    $t->expiration = $date_exp;
                    $t->time = $time;
                    $t->method = "voucher";
                    $t->routers = $v1['routers'];
                    $t->type = "PPPOE";
                    $t->save();
                }

                $v1->status = "1";
                $v1->user = $c['username'];
                $v1->save();
                // Telegram to Admin
                sendTelegram( $c['username']." Activate Voucher PPPOE\n".$p['name_plan'].
                "\nRouter: ".$v1['routers'].
                "\nPrice: ".$p['price']);
            }

            r2(U . "voucher/list-activated", 's', $_L['Activation_Vouchers_Successfully']);
        } else {
            r2(U . 'voucher/activation', 'e', $_L['Voucher_Not_Valid']);
        }
        break;

    case 'list-activated':
        $paginator = Paginator::bootstrap('tbl_transactions', 'username', $user['username']);
        $d = ORM::for_table('tbl_transactions')->where('username', $user['username'])->offset($paginator['startpoint'])->limit($paginator['limit'])->order_by_desc('id')->find_many();

        $ui->assign('d', $d);
        $ui->assign('paginator', $paginator);
        $ui->display('user-activation-list.tpl');

        break;

    default:
        $ui->display('404.tpl');
}
