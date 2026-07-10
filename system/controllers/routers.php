<?php

/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 **/

_admin();
$ui->assign('_title', Lang::T('Network'));
$ui->assign('_system_menu', 'network');

$action = $routes['1'];
$ui->assign('_admin', $admin);

require_once $DEVICE_PATH . DIRECTORY_SEPARATOR . "MikrotikHotspot.php";

if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
    _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
}

$leafletpickerHeader = <<<EOT
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css">
EOT;

switch ($action) {
    case 'add':
        run_hook('view_add_routers'); #HOOK
        $ui->display('admin/routers/add.tpl');
        break;

    case 'edit':
        $id  = $routes['2'];
        $d = ORM::for_table('tbl_routers')->find_one($id);
        if (!$d) {
            $d = ORM::for_table('tbl_routers')->where_equal('name', _get('name'))->find_one();
        }
        $ui->assign('xheader', $leafletpickerHeader);
        if ($d) {
            $ui->assign('d', $d);
            run_hook('view_router_edit'); #HOOK
            $ui->display('admin/routers/edit.tpl');
        } else {
            r2(getUrl('routers/list'), 'e', Lang::T('Account Not Found'));
        }
        break;

    case 'delete':
        $id  = $routes['2'];
        run_hook('router_delete'); #HOOK
        $d = ORM::for_table('tbl_routers')->find_one($id);
        if ($d) {
            $d->delete();
            r2(getUrl('routers/list'), 's', Lang::T('Data Deleted Successfully'));
        }
        break;

    case 'add-post':
        $name = _post('name');
        $ip_address = _post('ip_address');
        $username = _post('username');
        $password = _post('password');
        $description = _post('description');
        $enabled = _post('enabled');

        $msg = '';
        if (Validator::Length($name, 30, 1) == false) {
            $msg .= 'Name should be between 1 to 30 characters' . '<br>';
        }
        if($enabled || _post("testIt")){
            if ($ip_address == '' or $username == '') {
                $msg .= Lang::T('All field is required') . '<br>';
            }

            $d = ORM::for_table('tbl_routers')->where('ip_address', $ip_address)->find_one();
            if ($d) {
                $msg .= Lang::T('IP Router Already Exist') . '<br>';
            }
        }
        if (strtolower($name) == 'radius') {
            $msg .= '<b>Radius</b> name is reserved<br>';
        }

        if ($msg == '') {
            run_hook('add_router'); #HOOK
            if (_post("testIt")) {
                (new MikrotikHotspot())->getClient($ip_address, $username, $password);
            }
            $d = ORM::for_table('tbl_routers')->create();
            $d->name = $name;
            $d->ip_address = $ip_address;
            $d->username = $username;
            $d->password = $password;
            $d->description = $description;
            $d->enabled = $enabled;
            $d->save();

            r2(getUrl('routers/edit/') . $d->id(), 's', Lang::T('Data Created Successfully'));
        } else {
            r2(getUrl('routers/add'), 'e', $msg);
        }
        break;


    case 'generate_token':
        header('Content-Type: application/json');
        try {
            ORM::raw_execute("CREATE TABLE IF NOT EXISTS `tbl_provisioning_tokens` (
                `id` int NOT NULL AUTO_INCREMENT,
                `token` varchar(64) NOT NULL,
                `expires_at` datetime NOT NULL,
                `assigned_ip` varchar(32) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch(Exception $e) {}
        
        $token = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', time() + 3600);
        
        $last_router = ORM::for_table('tbl_routers')->where_like('ip_address', '10.66.66.%')->order_by_desc('id')->find_one();
        try {
            $next_ip = WgHelper::getNextIp($last_router ? $last_router->ip_address : null);
        } catch(Exception $e) {
            echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
            exit;
        }
        
        $d = ORM::for_table('tbl_provisioning_tokens')->create();
        $d->token = $token;
        $d->expires_at = $expires;
        $d->assigned_ip = $next_ip;
        $d->save();
        
        $url = rtrim($config['app_url'] ?? 'http://'.$_SERVER['HTTP_HOST'], '/') . '/provision.php?token=' . $token;
        echo json_encode(['status' => 'success', 'token' => $token, 'url' => $url]);
        exit;
        
    case 'check_token':
        header('Content-Type: application/json');
        $token = _get('token');
        $record = ORM::for_table('tbl_provisioning_tokens')->where('token', $token)->find_one();
        if (!$record) {
            echo json_encode(['status' => 'connected']);
        } else {
            echo json_encode(['status' => 'waiting']);
        }
        exit;

    case 'edit-post':
        $name = _post('name');
        $ip_address = _post('ip_address');
        $username = _post('username');
        $password = _post('password');
        $description = _post('description');
        $coordinates = _post('coordinates');
        $coverage = _post('coverage');
        $enabled = $_POST['enabled'];
        $msg = '';
        if (Validator::Length($name, 30, 4) == false) {
            $msg .= 'Name should be between 5 to 30 characters' . '<br>';
        }
        if($enabled || _post("testIt")){
            if ($ip_address == '' or $username == '') {
                $msg .= Lang::T('All field is required') . '<br>';
            }
        }

        $id = _post('id');
        $d = ORM::for_table('tbl_routers')->find_one($id);
        if ($d) {
        } else {
            $msg .= Lang::T('Data Not Found') . '<br>';
        }

        if ($d['name'] != $name) {
            $c = ORM::for_table('tbl_routers')->where('name', $name)->where_not_equal('id', $id)->find_one();
            if ($c) {
                $msg .= 'Name Already Exists<br>';
            }
        }
        $oldname = $d['name'];

        if($enabled || _post("testIt")){
            if ($d['ip_address'] != $ip_address) {
                $c = ORM::for_table('tbl_routers')->where('ip_address', $ip_address)->where_not_equal('id', $id)->find_one();
                if ($c) {
                    $msg .= 'IP Already Exists<br>';
                }
            }
        }

        if (strtolower($name) == 'radius') {
            $msg .= '<b>Radius</b> name is reserved<br>';
        }

        if ($msg == '') {
            run_hook('router_edit'); #HOOK
            if (_post("testIt")) {
                (new MikrotikHotspot())->getClient($ip_address, $username, $password);
            }
            $d->name = $name;
            $d->ip_address = $ip_address;
            $d->username = $username;
            $d->password = $password;
            $d->description = $description;
            $d->coordinates = $coordinates;
            $d->coverage = $coverage;
            $d->enabled = $enabled;
            $d->save();
            if ($name != $oldname) {
                $p = ORM::for_table('tbl_plans')->where('routers', $oldname)->find_result_set();
                $p->set('routers', $name);
                $p->save();
                $p = ORM::for_table('tbl_payment_gateway')->where('routers', $oldname)->find_result_set();
                $p->set('routers', $name);
                $p->save();
                $p = ORM::for_table('tbl_pool')->where('routers', $oldname)->find_result_set();
                $p->set('routers', $name);
                $p->save();
                $p = ORM::for_table('tbl_transactions')->where('routers', $oldname)->find_result_set();
                $p->set('routers', $name);
                $p->save();
                $p = ORM::for_table('tbl_user_recharges')->where('routers', $oldname)->find_result_set();
                $p->set('routers', $name);
                $p->save();
                $p = ORM::for_table('tbl_voucher')->where('routers', $oldname)->find_result_set();
                $p->set('routers', $name);
                $p->save();
            }
            r2(getUrl('routers/list'), 's', Lang::T('Data Updated Successfully'));
        } else {
            r2(getUrl('routers/edit/') . $id, 'e', $msg);
        }
        break;

    default:

        $name = _post('name');
        $name = _post('name');
        $query = ORM::for_table('tbl_routers')->order_by_desc('id');
        if ($name != '') {
            $query->where_like('name', '%' . $name . '%');
        }
        $d = Paginator::findMany($query, ['name' => $name]);
        $ui->assign('d', $d);
        run_hook('view_list_routers'); #HOOK
        $ui->display('admin/routers/list.tpl');
        break;
}
