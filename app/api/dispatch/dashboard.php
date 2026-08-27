<?php
if (in_array($action, ['stats','bookings','all-bookings','booking-detail','assign-driver','update-status','edit-fare','boost-fare','propose-fare','respond-fare','create-booking','delete-booking','cancel-ride','edit-booking','freeze-ride','unfreeze-ride','upcoming-rides','mark-reminder-sent','broadcast-push','send-custom-fcm','send-push','send-personal-push','bulk-push','bulk-whatsapp','bulk-tokens','save_fcm_token','fcm_token','users','user-detail','ban-user','delete-user','driver-payments','profile-update'])) {
    if (empty($_SESSION['user'])) jsonResponse(['error' => 'Admin auth required'], 401);
}
if ($action === 'stats') {
    $conn = db();
    $total = intval($conn->query('SELECT COUNT(*) as c FROM app_bookings')->fetch_assoc()['c']);
    $completed = intval($conn->query("SELECT COUNT(*) as c FROM app_bookings WHERE UPPER(status) = 'COMPLETED'")->fetch_assoc()['c']);
    $inTransit = intval($conn->query("SELECT COUNT(*) as c FROM app_bookings WHERE UPPER(status) IN ('IN_TRANSIT','ON_TRIP','ARRIVED')")->fetch_assoc()['c']);
    $assigned = intval($conn->query("SELECT COUNT(*) as c FROM app_bookings WHERE UPPER(status) IN ('CONFIRMED','ASSIGNED','ACCEPTED','DRIVER_ASSIGNED')")->fetch_assoc()['c']);
    $cancelledTotal = intval($conn->query("SELECT COUNT(*) as c FROM app_bookings WHERE UPPER(status) LIKE 'CANCEL%' OR UPPER(status) = 'REJECTED'")->fetch_assoc()['c']);
    $cancelledUser = intval($conn->query("SELECT COUNT(*) as c FROM app_bookings WHERE UPPER(status) = 'CANCELLED_BY_USER'")->fetch_assoc()['c']);
    $cancelledAdmin = intval($conn->query("SELECT COUNT(*) as c FROM app_bookings WHERE UPPER(status) IN ('CANCELLED_BY_ADMIN','CANCELLED')")->fetch_assoc()['c']);
    $pending = max(0, $total - $completed - $inTransit - $assigned - $cancelledTotal);
    $revenue = floatval($conn->query("SELECT IFNULL(SUM(total_fare),0) as t FROM app_bookings WHERE UPPER(status) = 'COMPLETED'")->fetch_assoc()['t']);
    $driversAvail = intval($conn->query("SELECT COUNT(*) as c FROM app_drivers WHERE LOWER(status) = 'available'")->fetch_assoc()['c']);
    $driversTotal = intval($conn->query("SELECT COUNT(*) as c FROM app_drivers")->fetch_assoc()['c']);
    jsonResponse(['total'=>$total,'pending'=>$pending,'assigned'=>$assigned,'inTransit'=>$inTransit,'active'=>$assigned+$inTransit,'completed'=>$completed,'cancelledUser'=>$cancelledUser,'cancelledAdmin'=>$cancelledAdmin,'cancelledTotal'=>$cancelledTotal,'totalRevenue'=>$revenue,'availableDrivers'=>$driversAvail,'totalDrivers'=>$driversTotal]);
}
if ($action === 'bookings' || $action === 'all-bookings') {
    $sf2 = trim($_GET['status'] ?? $b['status'] ?? '');
    $sfz = isset($_GET['is_frozen']) ? trim($_GET['is_frozen']) : (isset($b['is_frozen']) ? trim($b['is_frozen']) : '');
    $sq = trim($_GET['search'] ?? $b['search'] ?? '');
    $sd = trim($_GET['start_date'] ?? '');
    $ed = trim($_GET['end_date'] ?? '');
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(200, max(10, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    $conds = []; $types = ''; $params = [];
    if ($sf2) { $sU = strtoupper($sf2); if ($sU === 'PENDING') { $conds[] = "(b.status IS NULL OR TRIM(b.status) = '' OR (UPPER(b.status) NOT IN ('COMPLETED','IN_TRANSIT','ON_TRIP','ARRIVED','CONFIRMED','ASSIGNED','ACCEPTED','DRIVER_ASSIGNED','CANCELLED','CANCELLED_BY_USER','CANCELLED_BY_ADMIN','REJECTED') AND UPPER(b.status) NOT LIKE 'CANCEL%'))"; } else { $conds[] = "UPPER(b.status) = ?"; $types .= 's'; $params[] = $sU; } }
    if ($sq) { $w = "%{$sq}%"; $conds[] = "(b.customer_name LIKE ? OR b.customer_phone LIKE ? OR b.booking_ref LIKE ?)"; $types .= 'sss'; $params[] = $w; $params[] = $w; $params[] = $w; }
    if ($sd) { $conds[] = "(b.pickup_date >= ? OR DATE(b.created_at) >= ?)"; $types .= 'ss'; $params[] = $sd; $params[] = $sd; }
    if ($ed) { $conds[] = "(b.pickup_date <= ? OR DATE(b.created_at) <= ?)"; $types .= 'ss'; $params[] = $ed; $params[] = $ed; }
    if ($sfz !== '') { $conds[] = "b.is_frozen = ?"; $types .= 'i'; $params[] = $sfz === '1' ? 1 : 0; }
    $wc = !empty($conds) ? " WHERE " . implode(" AND ", $conds) : '';
    $conn = db();
    $tq = "SELECT COUNT(*) as c FROM app_bookings b" . $wc;
    if ($types) { $cs = $conn->prepare($tq); if ($cs) { $cs->bind_param($types, ...$params); $cs->execute(); $total = intval($cs->get_result()->fetch_assoc()['c']); } else { $total = 0; } } else { $total = intval($conn->query($tq)->fetch_assoc()['c']); }
    $sql = "SELECT b.*, COALESCE(NULLIF(b.driver_name,''),d.name) as driver_name, COALESCE(NULLIF(b.driver_phone,''),d.phone) as driver_phone, COALESCE(NULLIF(b.vehicle_number,''),d.plate_number,'') as vehicle_number, d.name as assigned_driver_name, d.phone as assigned_driver_phone, d.car_model, d.plate_number FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id" . $wc . " ORDER BY b.id DESC LIMIT $limit OFFSET $offset";
    $rows = $types ? dbRows($sql, $types, $params) : dbRows($sql);
    jsonResponse(['bookings'=>$rows,'total'=>$total,'page'=>$page,'limit'=>$limit,'pages'=>ceil($total/$limit)]);
}
if ($action === 'booking-detail') {
    $bid = intval($_GET['id'] ?? $b['id'] ?? 0);
    if (!$bid) jsonResponse(['error'=>'id required'], 400);
    $rows = dbRows("SELECT b.*, COALESCE(NULLIF(b.driver_name,''),d.name) as driver_name, COALESCE(NULLIF(b.driver_phone,''),d.phone) as driver_phone, COALESCE(NULLIF(b.vehicle_number,''),d.plate_number) as vehicle_number, d.car_model, d.plate_number FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE b.id = ?", 'i', [$bid]);
    if (empty($rows)) jsonResponse(['error'=>'Not found'], 404);
    jsonResponse(['booking'=>$rows[0]]);
}
if ($action === 'assign-driver' && $method === 'POST') {
    $bid = intval($b['booking_id'] ?? 0);
    $did = isset($b['driver_id']) ? intval($b['driver_id']) : 0;
    $dn = trim($b['driver_name'] ?? '');
    $dp = trim($b['driver_phone'] ?? '');
    $vn = trim($b['vehicle_number'] ?? '');
    if (!$bid) jsonResponse(['error'=>'booking_id required'], 400);
    $conn = db();
    $vmodel = '';
    $fid = $did > 0 ? $did : null;
    if ($fid && (!$dn || !$dp)) {
        $dr = dbRows("SELECT name,phone,plate_number,car_model FROM app_drivers WHERE id = ?", 'i', [$fid]);
        if (!empty($dr)) { if (!$dn) $dn=$dr[0]['name']; if (!$dp) $dp=$dr[0]['phone']; if (!$vn) $vn=$dr[0]['plate_number']??''; $vmodel=$dr[0]['car_model']??''; dbExec("UPDATE app_drivers SET status='on_trip' WHERE id = ?", 'i', [$fid]); }
    }
    if (!$dn || !$dp) jsonResponse(['error'=>'Driver name and phone required'], 400);
    $c10 = substr(preg_replace('/\D/','',$dp),-10);
    if ($c10) {
        $sf=$conn->prepare("SELECT id, car_model FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone,'+',''),' ',''),'-',''),10)=?");
        $sf->bind_param('s',$c10); $sf->execute(); $sr=$sf->get_result();
        if ($sr && $row=$sr->fetch_assoc()) { $fid=intval($row['id']); if (!$vmodel) $vmodel=$row['car_model']??''; $su=$conn->prepare("UPDATE app_drivers SET name=?,plate_number=?,status='on_trip' WHERE id=?"); $su->bind_param('ssi',$dn,$vn,$fid); $su->execute(); }
        else { $si=$conn->prepare("INSERT INTO app_drivers (name,phone,car_model,plate_number,status) VALUES (?,?,'Goa Cab',?,'on_trip')"); $si->bind_param('sss',$dn,$dp,$vn); $si->execute(); $fid=intval($conn->insert_id); $vmodel='Goa Cab'; }
    }
    $stmt=$conn->prepare("UPDATE app_bookings SET 
status='CONFIRMED',driver_id=?,driver_name=?,driver_phone=?,vehicle_number=?,vehicle_model=?,driver_decision='ACCEPTED',assigned_by='admin' WHERE id=?");
    $stmt->bind_param('isssssi',$fid,$dn,$dp,$vn,$vmodel,$bid); $stmt->execute();
    $rows=dbRows('SELECT * FROM app_bookings WHERE id=?','i',[$bid]);
    if (empty($rows)) jsonResponse(['error'=>'Not found'], 404);
    $bk=$rows[0];
    $idt=formatIndianDateTime($bk['pickup_date'],$bk['pickup_time']);
    $tc="PAVANCAB RIDE DISPATCHED!\n\nRef: #{$bk['booking_ref']}\nCab: {$bk['cab_type']} ($vn)\nDriver: $dn ($dp)\nPickup: {$bk['pickup_location']}\nDrop: {$bk['drop_location']}\nDate: $idt\nFare: Rs.{$bk['total_fare']}\n\nYour driver is en route!";
    $dso='';
    if ($fid) { $dn2=date('Y-m-d'); $ds=dbRows("SELECT id FROM driver_subscriptions WHERE driver_id=? AND status='active' AND end_date>=? LIMIT 1",'is',[$fid,$dn2]); if (empty($ds)) { $dc=dbRows("SELECT setting_key,setting_value FROM app_settings WHERE setting_key IN ('driver_subscription_amount','driver_commission_per_ride')"); $dm=[]; foreach($dc as $rw) $dm[$rw['setting_key']]=$rw['setting_value']; $sa=intval($dm['driver_subscription_amount']??1000); $ca=intval($dm['driver_commission_per_ride']??200); $dso="\n\nSUBSCRIPTION OFFER\nPay Rs.$sa/month unlimited rides! Or Rs.$ca per ride commission.\nOpen Driver App."; } }
    $td="PAVANCAB DRIVER DISPATCH\n\nRef: #{$bk['booking_ref']}\nCab: {$bk['cab_type']} ($vn)\nPassenger: {$bk['customer_name']} ({$bk['customer_phone']})\nPickup: {$bk['pickup_location']}\nDrop: {$bk['drop_location']}\nTime: $idt\nFare: Rs.{$bk['total_fare']}\n\nAssigned by PAVANCAB Dispatch Tower.".$dso;
    @sendMetaWhatsApp($bk['customer_phone'],$tc);
    @sendMetaWhatsApp($dp,$td);
    broadcastRideLifecycleFCM('DRIVER_ASSIGNED',$bid);
    jsonResponse(['success'=>true,'message'=>"Driver $dn assigned!",'booking'=>$bk]);
}
if ($action === 'update-status' && $method === 'POST') {
    $bid2=intval($b['booking_id']??0); $st=trim($b['status']??'');
    if (!$bid2||!$st) jsonResponse(['error'=>'booking_id and status required'],400);
    $fs=($st==='CANCELLED')?'CANCELLED_BY_ADMIN':$st;
    dbExec('UPDATE app_bookings SET status=? WHERE id=?','si',[$fs,$bid2]);
    if ($fs==='PENDING') { $r=dbRows('SELECT driver_id FROM app_bookings WHERE id=?','i',[$bid2]); if(!empty($r)&&$r[0]['driver_id']) dbExec("UPDATE app_drivers SET status='available' WHERE id=?",'i',[$r[0]['driver_id']]); dbExec("UPDATE app_bookings SET driver_id=NULL,driver_name=NULL,driver_phone=NULL,driver_decision='NONE' WHERE id=?",'i',[$bid2]); broadcastRideLifecycleFCM('RIDE_RESET',$bid2); }
    elseif (strpos(strtoupper($fs),'CANCEL')===0) { $r=dbRows('SELECT driver_id FROM app_bookings WHERE id=?','i',[$bid2]); if(!empty($r)&&$r[0]['driver_id']) dbExec("UPDATE app_drivers SET status='available' WHERE id=?",'i',[$r[0]['driver_id']]); dbExec("UPDATE app_bookings SET driver_id=NULL,driver_name=NULL,driver_phone=NULL,driver_decision='NONE' WHERE id=?",'i',[$bid2]); broadcastRideLifecycleFCM('CANCELLED_BY_ADMIN',$bid2); }
    elseif ($fs==='COMPLETED') { $r=dbRows('SELECT driver_id FROM app_bookings WHERE id=?','i',[$bid2]); if(!empty($r)&&$r[0]['driver_id']) dbExec("UPDATE app_drivers SET status='available' WHERE id=?",'i',[$r[0]['driver_id']]); broadcastRideLifecycleFCM('RIDE_COMPLETED',$bid2); }
    elseif ($fs==='IN_TRANSIT') { $r=dbRows('SELECT driver_id FROM app_bookings WHERE id=?','i',[$bid2]); if(!empty($r)&&$r[0]['driver_id']) dbExec("UPDATE app_drivers SET status='on_trip' WHERE id=?",'i',[$r[0]['driver_id']]); broadcastRideLifecycleFCM('RIDE_STARTED',$bid2); }
    $upd=dbRows('SELECT * FROM app_bookings WHERE id=?','i',[$bid2]);
    jsonResponse(['success'=>true,'message'=>"Status updated to $fs",'booking'=>$upd[0]??null]);
}
if ($action === 'edit-fare' && $method === 'POST') {
    $bid3=intval($b['booking_id']??0); $nf=floatval($b['new_fare']??0); $ba=floatval($b['boost_amount']??0); $re=trim($b['reason']??'Admin adjustment');
    if (!$bid3) jsonResponse(['error'=>'booking_id required'],400);
    $rows=dbRows('SELECT * FROM app_bookings WHERE id=?','i',[$bid3]);
    if (empty($rows)) jsonResponse(['error'=>'Not found'],404);
    $ff=$nf>0?$nf:(floatval($rows[0]['total_fare']??0)+$ba);
    if ($ff<=0) jsonResponse(['error'=>'Valid fare required'],400);
    $ne="\n[FARE ADJUSTMENT] Updated to Rs.$ff ($re) at ".date('h:i A');
    $conn=db(); $stmt=$conn->prepare("UPDATE app_bookings SET total_fare=?,special_notes=CONCAT(IFNULL(special_notes,''),?) WHERE id=?");
    $stmt->bind_param('dsi',$ff,$ne,$bid3); $stmt->execute();
    if (empty($rows[0]['driver_id']) && strtoupper(trim($rows[0]['status'] ?? 'PENDING')) === 'PENDING') {
        $conn->query("UPDATE app_bookings SET driver_release_ends_at = DATE_ADD(NOW(), INTERVAL 2 MINUTE) WHERE id=$bid3");
    }
    broadcastRideLifecycleFCM('FARE_UPDATED',$bid3,['reason'=>$re]);
    $upd=dbRows('SELECT * FROM app_bookings WHERE id=?','i',[$bid3]);
    jsonResponse(['success'=>true,'message'=>"Fare updated to Rs.$ff",'booking'=>$upd[0]]);
}
if ($action === 'propose-fare' && $method === 'POST') {
    $bid4=intval($b['booking_id']??0); $pf=floatval($b['proposed_fare']??0); $re2=trim($b['reason']??'Driver asking minimum fare');
    if (!$bid4||$pf<=0) jsonResponse(['error'=>'booking_id and proposed_fare required'],400);
    $rows=dbRows('SELECT * FROM app_bookings WHERE id=?','i',[$bid4]);
    if (empty($rows)) jsonResponse(['error'=>'Not found'],404);
    $bk2=$rows[0]; if ($pf<=floatval($bk2['total_fare']??0)) jsonResponse(['error'=>'Must be higher'],400);
    $pb=determineUserRole(trim($b['proposed_by']??''));
    $conn=db(); $stmt=$conn->prepare("UPDATE app_bookings SET proposed_fare=?,fare_proposal_status='PENDING',fare_proposed_by=?,fare_proposal_reason=? WHERE id=?");
    $stmt->bind_param('dssi',$pf,$pb,$re2,$bid4); $stmt->execute();
    // 2-MIN AUTO-RELEASE: offer stays available to drivers for 2 min; if still unassigned it auto-releases to pending.
    if (empty($bk2['driver_id']) && strtoupper(trim($bk2['status'] ?? 'PENDING')) === 'PENDING') {
        $conn->query("UPDATE app_bookings SET driver_release_ends_at = DATE_ADD(NOW(), INTERVAL 2 MINUTE) WHERE id=$bid4");
    }
    $ref=$bk2['booking_ref']; $tk=[];
    if (!empty($bk2['user_email'])) $tk=array_merge($tk,getFCMTokensByEmail($bk2['user_email']));
    if (!empty($bk2['customer_phone'])) $tk=array_merge($tk,getFCMTokensByPhone($bk2['customer_phone']));
    $tk=array_values(array_unique(array_map('trim',array_filter($tk))));
    if (!empty($tk)) sendFCMPush($tk,"Driver asking Rs.$pf for Ride #$ref","Current fare: Rs.{$bk2['total_fare']}. Accept?",['type'=>'FARE_PROPOSED','booking_id'=>strval($bid4),'proposed_fare'=>strval($pf),'url'=>'https://pavancab.com/app/']);
    jsonResponse(['success'=>true,'message'=>"Proposal of Rs.$pf sent!"]);
}
if ($action === 'respond-fare' && $method === 'POST') {
    $bid5=intval($b['booking_id']??0); $res= strtoupper(trim($b['response']??'')); $nf2=floatval($b['new_fare']??0);
    if (!$bid5||!in_array($res,['ACCEPT','DECLINE'])) jsonResponse(['error'=>'booking_id and response required'],400);
    $conn=db();
    if ($res==='ACCEPT'&&$nf2>0) $conn->query("UPDATE app_bookings SET total_fare=$nf2,fare_proposal_status='ACCEPTED' WHERE id=$bid5");
    else $conn->query("UPDATE app_bookings SET fare_proposal_status='DECLINED' WHERE id=$bid5");
    jsonResponse(['success'=>true,'message'=>"Fare $res'd"]);
}
// FREEZE / UNFREEZE — admin/team can hold a ride so it is NOT released to the driver app.
// While frozen the ride stays out of drivers' reach; dispatch can still assign it to any driver.
if (($action === 'freeze-ride' || $action === 'unfreeze-ride') && $method === 'POST') {
    $bidF=intval($b['booking_id']??0);
    if (!$bidF) jsonResponse(['error'=>'booking_id required'],400);
    $fz = ($action === 'freeze-ride') ? 1 : 0;
    dbExec('UPDATE app_bookings SET is_frozen=? WHERE id=?','ii',[$fz,$bidF]);
    $rF=dbRows('SELECT * FROM app_bookings WHERE id=?','i',[$bidF]);
    broadcastRideLifecycleFCM($fz ? 'RIDE_FROZEN' : 'RIDE_UNFROZEN', $bidF);
    jsonResponse(['success'=>true,'message'=>$fz?'Ride frozen — not released to drivers until unfrozen.':'Ride unfrozen and released to drivers.','is_frozen'=>$fz,'booking'=>$rF[0]??null]);
}
if ($action === 'create-booking' && $method === 'POST') {
    $nm=trim($b['customer_name']??'Walk-in'); $ph=trim($b['customer_phone']??''); $pk=trim($b['pickup_location']??''); $dr2=trim($b['drop_location']??'Goa');
    $dt=trim($b['pickup_date']??date('Y-m-d')); $tm=trim($b['pickup_time']??date('H:i')); $cb=trim($b['cab_type']??'Sedan'); $fa=floatval($b['total_fare']??0);
    $tt=trim($b['trip_type']??'one_way'); $nt=trim($b['special_notes']??'Manual Booking'); $bbn=trim($b['booked_by_name']??''); $bbp=trim($b['booked_by_phone']??'');
    if (!$ph||!$pk||$fa<=0) jsonResponse(['error'=>'Phone, pickup and fare required'],400);
    $bref='GTA-'.date('ymd').'-'.str_pad(rand(0,9999),4,'0',STR_PAD_LEFT);
    $conn=db(); $stmt=$conn->prepare("INSERT INTO app_bookings (booking_ref,user_email,customer_name,customer_phone,trip_type,pickup_location,drop_location,pickup_date,pickup_time,cab_type,total_fare,special_notes,status,booking_source,booked_by_phone,booked_by_name) VALUES (?,'admin@pavancab.com',?,?,?,?,?,?,?,?,?,?,'PENDING','phone',?,?)");
    $stmt->bind_param('ssssssssssdss',$bref,$nm,$ph,$tt,$pk,$dr2,$dt,$tm,$cb,$fa,$nt,$bbp,$bbn); $stmt->execute();
    $nid=$conn->insert_id;
    notifyAdminAndTeamNewBooking($nid,$bref,$nm,$ph,$pk,$dr2,$dt,$tm,$cb,$fa,$bbp,$bbn);
    $upd=dbRows('SELECT * FROM app_bookings WHERE id=?','i',[$nid]);
    jsonResponse(['success'=>true,'message'=>"Booking #$bref created!",'booking'=>$upd[0]]);
}
if ($action === 'delete-booking' && $method === 'POST') { $bid6=intval($b['booking_id']??0); if (!$bid6) jsonResponse(['error'=>'required'],400); db()->query("DELETE FROM app_bookings WHERE id=$bid6"); jsonResponse(['success'=>true]); }
if ($action === 'cancel-ride' && $method === 'POST') { $bid7=intval($b['booking_id']??0); if (!$bid7) jsonResponse(['error'=>'required'],400); $r=dbRows('SELECT driver_id FROM app_bookings WHERE id=?','i',[$bid7]); if(!empty($r)&&$r[0]['driver_id']) dbExec("UPDATE app_drivers SET status='available' WHERE id=?",'i',[$r[0]['driver_id']]); dbExec("UPDATE app_bookings SET status='CANCELLED_BY_ADMIN',driver_id=NULL,driver_name=NULL,driver_phone=NULL,driver_decision='NONE' WHERE id=?",'i',[$bid7]); broadcastRideLifecycleFCM('CANCELLED_BY_ADMIN',$bid7); jsonResponse(['success'=>true,'message'=>'Cancelled']); }
if ($action === 'edit-booking' && $method === 'POST') { $bid8=intval($b['booking_id']??0); if (!$bid8) jsonResponse(['error'=>'required'],400); $conn=db(); $stmt=$conn->prepare("UPDATE app_bookings SET customer_name=?,customer_phone=?,pickup_location=?,drop_location=?,pickup_date=?,pickup_time=?,cab_type=?,total_fare=?,special_notes=? WHERE id=?"); $stmt->bind_param('sssssssdsi',$b['customer_name']??'',$b['customer_phone']??'',$b['pickup_location']??'',$b['drop_location']??'',$b['pickup_date']??date('Y-m-d'),$b['pickup_time']??date('H:i'),$b['cab_type']??'Sedan',floatval($b['total_fare']??0),$b['special_notes']??'',$bid8); $stmt->execute(); jsonResponse(['success'=>true]); }
if ($action === 'upcoming-rides') {
    $conn=db(); $today=date('Y-m-d'); $tmr=date('Y-m-d',strtotime('+1 day')); $nowTs=time(); $rem=[];
    $as=dbRows("SELECT b.*,COALESCE(NULLIF(b.driver_name,''),d.name) as driver_name,COALESCE(NULLIF(b.driver_phone,''),d.phone) as driver_phone FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id=d.id WHERE UPPER(b.status) IN ('CONFIRMED','ASSIGNED','ACCEPTED') AND (b.reminder_sent IS NULL OR b.reminder_sent=0) AND b.pickup_date IN (?,?) ORDER BY b.pickup_date,b.pickup_time",'ss',[$today,$tmr]);
    foreach($as as $ri) { $df=(strtotime($ri['pickup_date'].' '.$ri['pickup_time'])-$nowTs)/60; if(($nowTs-strtotime($ri['created_at']??'now'))/60<30) continue; if($df>0&&$df<=60) { $ri['reminder_type']='ride_soon'; $rem[]=$ri; } }
    $pend=dbRows("SELECT b.* FROM app_bookings b WHERE UPPER(b.status)='PENDING' AND b.pickup_date IN (?,?) AND (b.reminder_sent IS NULL OR b.reminder_sent<3) ORDER BY b.pickup_date,b.pickup_time",'ss',[$today,$tmr]);
    foreach($pend as $ri) { $df=(strtotime($ri['pickup_date'].' '.$ri['pickup_time'])-$nowTs)/60; if(($nowTs-strtotime($ri['created_at']??'now'))/60<30) continue; if($df>0&&$df<=90) { $ri['reminder_type']='unassigned_urgent'; $rem[]=$ri; continue; } if($df>0&&$df<=360) { $ri['reminder_type']='unassigned'; $rem[]=$ri; } }
    jsonResponse(['rides'=>$rem,'count'=>count($rem)]);
}
if ($action === 'mark-reminder-sent' && $method === 'POST') { $bid9=intval($b['booking_id']??0); if (!$bid9) jsonResponse(['error'=>'required'],400); dbExec("UPDATE app_bookings SET reminder_sent=IFNULL(reminder_sent,0)+1 WHERE id=?",'i',[$bid9]); jsonResponse(['success'=>true]); }
if ($action === 'broadcast-push' && $method === 'POST') { $ti=trim($b['title']??'PAVANCAB Alert'); $bo=trim($b['body']??''); if (!$bo) jsonResponse(['error'=>'body required'],400); $tk=[]; $r=db()->query("SELECT DISTINCT fcm_token FROM app_fcm_tokens WHERE fcm_token IS NOT NULL AND fcm_token!=''"); if($r) while($rw=$r->fetch_assoc()) $tk[]=$rw['fcm_token']; if(empty($tk)) jsonResponse(['success'=>true,'sent'=>0]); $res=sendFCMPush($tk,$ti,$bo,['type'=>'BROADCAST']); jsonResponse(['success'=>true,'sent'=>$res['sent'],'failed'=>$res['failed']]); }
if ($action === 'send-custom-fcm' || $action === 'send-push') {
    $tp=cleanPhoneDigits($b['target_phone']??$b['phone']??''); $te=trim($b['target_email']??$b['email']??''); $tt2=trim($b['target_token']??$b['token']??$b['fcm_token']??'');
    $ba2=!empty($b['broadcast_all']); $bo2=!empty($b['broadcast_online']); $ti2=trim($b['title']??'PAVANCAB Alert'); $bo3=trim($b['body']??$b['message']??''); $ca=trim($b['click_action']??$b['url']??'/app/');
    if (!$bo3) jsonResponse(['error'=>'body required'],400); $tk2=[]; $conn=db();
    if ($tt2) { $tk2[]=$tt2; } elseif($ba2) { $r=$conn->query("SELECT DISTINCT fcm_token FROM app_fcm_tokens WHERE fcm_token!=''"); if($r) while($rw=$r->fetch_assoc()) $tk2[]=$rw['fcm_token']; }
    elseif($bo2) { $r=$conn->query("SELECT DISTINCT fcm_token FROM app_fcm_tokens WHERE is_online=1 AND last_active_at>=NOW()-INTERVAL 5 MINUTE AND fcm_token!=''"); if($r) while($rw=$r->fetch_assoc()) $tk2[]=$rw['fcm_token']; }
    else { if($tp) $tk2=array_merge($tk2,getFCMTokensByPhone($tp)); if($te) $tk2=array_merge($tk2,getFCMTokensByEmail($te)); }
    $tk2=array_values(array_unique(array_filter($tk2))); if(empty($tk2)) jsonResponse(['error'=>'No active device'],404);
    $res=sendFCMPush($tk2,$ti2,$bo3,['type'=>'CUSTOM_ANNOUNCEMENT','click_action'=>$ca,'url'=>$ca]); jsonResponse(['success'=>true,'sent'=>$res['sent'],'failed'=>$res['failed']]);
}
if ($action === 'send-personal-push' && $method === 'POST') { $up=trim($b['user_phone']??''); $ue=trim($b['user_email']??''); $ti3=trim($b['title']??''); $bo4=trim($b['body']??''); if(!$ti3||!$bo4) jsonResponse(['error'=>'title and body required'],400); $tk3=[]; if(!empty($ue)) $tk3=array_merge($tk3,getFCMTokensByEmail($ue)); if(!empty($up)) $tk3=array_merge($tk3,getFCMTokensByPhone($up)); $tk3=array_values(array_unique(array_filter($tk3))); if(empty($tk3)) jsonResponse(['error'=>'No device'],404); sendFCMPush($tk3,$ti3,$bo4,['type'=>'PERSONAL_MESSAGE']); jsonResponse(['success'=>true,'sent'=>count($tk3)]); }
if ($action === 'bulk-push' && $method === 'POST') { $btk=$b['tokens']??[]; $ti4=$b['title']??''; $bo5=$b['body']??''; if(empty($btk)||!$ti4||!$bo5) jsonResponse(['error'=>'required'],400); if(!is_array($btk)) $btk=[$btk]; $ctk=array_values(array_filter(array_unique(array_map('trim',$btk)))); sendFCMPush($ctk,$ti4,$bo5,['type'=>'ADMIN_BROADCAST']); jsonResponse(['success'=>true,'sent'=>count($ctk)]); }
if ($action === 'bulk-whatsapp' && $method === 'POST') { $bps=$b['phones']??[]; $me=$b['message']??''; if(empty($bps)||!$me) jsonResponse(['error'=>'required'],400); if(!is_array($bps)) $bps=[$bps]; $cp=array_filter(array_unique(array_map(function($p){return substr(preg_replace('/\D/','',$p),-10);},$bps))); sendMetaWhatsAppParallel(array_values($cp),$me); jsonResponse(['success'=>true,'sent'=>count($cp)]); }
if ($action === 'bulk-tokens' && $method === 'POST') { $bps2=$b['phones']??[]; $ems=$b['emails']??[]; if(!is_array($bps2)) $bps2=[$bps2]; if(!is_array($ems)) $ems=[$ems]; $conn=db(); $tk4=[]; foreach($bps2 as $p){$c='%'.substr(preg_replace('/\D/','',$p),-10).'%';$r=$conn->query("SELECT fcm_token FROM app_fcm_tokens WHERE user_mobile LIKE '$c' AND fcm_token!=''");if($r)while($rw=$r->fetch_assoc())if($rw['fcm_token'])$tk4[]=$rw['fcm_token'];} foreach($ems as $e){$c=$conn->real_escape_string(strtolower(trim($e)));$r=$conn->query("SELECT fcm_token FROM app_fcm_tokens WHERE LOWER(user_email)='$c' AND fcm_token!=''");if($r)while($rw=$r->fetch_assoc())if($rw['fcm_token'])$tk4[]=$rw['fcm_token'];} jsonResponse(['tokens'=>array_values(array_unique($tk4))]); }
if ($action === 'save_fcm_token' || $action === 'fcm_token') { if(empty($_SESSION['user'])) jsonResponse(['error'=>'Login required'],401); $ft=trim($b['fcm_token']??$b['token']??''); $ue2=trim($b['user_email']??$b['email']??($_SESSION['user']['email']??'')); $up2=cleanPhoneDigits($b['user_mobile']??$b['phone']??($_SESSION['user']['mobile']??'')); if(!$ft) jsonResponse(['success'=>false,'message'=>'No token'],200); $conn=db(); $stmt=$conn->prepare("INSERT INTO app_fcm_tokens (fcm_token,user_email,user_mobile) VALUES (?,?,?) ON DUPLICATE KEY UPDATE user_email=VALUES(user_email),user_mobile=VALUES(user_mobile),updated_at=NOW()"); if($stmt){$stmt->bind_param('sss',$ft,$ue2,$up2);$stmt->execute();} jsonResponse(['success'=>true]); }
if ($action === 'users') {
    $conn=db(); $um=[]; $ru=$conn->query("SELECT id,name,mobile,email,role,fcm_token,is_online,last_active_at,device_info,created_at,IFNULL(is_banned,0) as is_banned FROM app_users ORDER BY id DESC");
    if($ru)while($u=$ru->fetch_assoc()){ $c10=subStr(preg_replace('/\D/','',$u['mobile']??''),-10); $ky=$c10?:('email_'.strtolower(trim($u['email']??''))); if(!$ky||$ky==='email_') $ky='user_'.$u['id']; $um[$ky]=['user_id'=>intval($u['id']),'name'=>$u['name']?:'Customer','mobile'=>$u['mobile']??'','email'=>$u['email']??'','is_online'=>intval($u['is_online']??0),'is_banned'=>intval($u['is_banned']??0),'last_active_at'=>$u['last_active_at']??$u['created_at'],'created_at'=>$u['created_at'],'total_bookings'=>0,'completed_bookings'=>0,'cancelled_bookings'=>0,'total_spent'=>0]; }
    $rb=$conn->query("SELECT id,booking_ref,customer_name,customer_phone,user_email,total_fare,status,created_at FROM app_bookings ORDER BY id DESC");
    if($rb)while($br=$rb->fetch_assoc()){ $c10=subStr(preg_replace('/\D/','',$br['customer_phone']??''),-10); $ky=$c10?:('email_'.strtolower(trim($br['user_email']??''))); if(!$ky||$ky==='email_') continue; if(!isset($um[$ky])) $um[$ky]=['user_id'=>0,'name'=>$br['customer_name']??'Passenger','mobile'=>$br['customer_phone']??'','email'=>$br['user_email']??'','is_online'=>0,'is_banned'=>0,'created_at'=>$br['created_at'],'total_bookings'=>0,'completed_bookings'=>0,'cancelled_bookings'=>0,'total_spent'=>0]; $um[$ky]['total_bookings']++; $uk=classifyRideStatus($br['status']); if($uk==='COMPLETED'){$um[$ky]['completed_bookings']++;$um[$ky]['total_spent']+=floatval($br['total_fare']);}elseif($uk==='CANCELLED')$um[$ky]['cancelled_bookings']++; }
    jsonResponse(array_values($um));
}
if ($action === 'ban-user' && $method === 'POST') { $uid=intval($b['user_id']??0); $bn=intval($b['ban']??1); if(!$uid) jsonResponse(['error'=>'required'],400); db()->query("UPDATE app_users SET is_banned=$bn WHERE id=$uid"); jsonResponse(['success'=>true]); }
if ($action === 'delete-user' && $method === 'POST') { $uid2=intval($b['user_id']??0); if(!$uid2) jsonResponse(['error'=>'required'],400); $conn=db(); $conn->query("DELETE FROM app_users WHERE id=$uid2"); jsonResponse(['success'=>true]); }
if ($action === 'user-detail') { $ph=cleanPhoneDigits($_GET['phone']??$b['phone']??''); $uid3=intval($_GET['id']??$b['id']??0); $c10=$ph?substr($ph,-10):''; $conn=db(); $ur=null; if($uid3>0){$r=dbRows("SELECT * FROM app_users WHERE id=? LIMIT 1",'i',[$uid3]);if(!empty($r))$ur=$r[0];} if(!$ur&&$c10){$r=dbRows("SELECT * FROM app_users WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile,'+',''),' ',''),'-',''),10)=? LIMIT 1",'s',[$c10]);if(!empty($r))$ur=$r[0];} $bk=$c10?dbRows("SELECT * FROM app_bookings WHERE RIGHT(REPLACE(REPLACE(REPLACE(customer_phone,'+',''),' ',''),'-',''),10)=? ORDER BY id DESC",'s',[$c10]):[]; jsonResponse(['user'=>$ur,'bookings'=>$bk]); }
if ($action === 'driver-payments') { $did2=intval($b['driver_id']??$_GET['driver_id']??0); if($did2){$p=dbRows("SELECT * FROM driver_payments WHERE driver_id=? ORDER BY created_at DESC LIMIT 100",'i',[$did2]);$s=dbRows("SELECT * FROM driver_subscriptions WHERE driver_id=? ORDER BY created_at DESC LIMIT 50",'i',[$did2]);}else{$p=dbRows("SELECT dp.*,d.name as driver_name,d.phone as driver_phone FROM driver_payments dp LEFT JOIN app_drivers d ON dp.driver_id=d.id ORDER BY dp.created_at DESC LIMIT 100");$s=dbRows("SELECT ds.*,d.name as driver_name,d.phone as driver_phone FROM driver_subscriptions ds LEFT JOIN app_drivers d ON ds.driver_id=d.id ORDER BY ds.created_at DESC LIMIT 50");} jsonResponse(['payments'=>$p,'subscriptions'=>$s]); }
if ($action === 'boost-fare' && $method === 'POST') { $bid10=intval($b['booking_id']??0); $bf=floatval($b['boost_amount']??0); if(!$bid10||$bf<=0) jsonResponse(['error'=>'booking_id and boost_amount required'],400); $conn=db(); $stmt=$conn->prepare("UPDATE app_bookings SET total_fare=total_fare+?,special_notes=CONCAT(IFNULL(special_notes,''),?) WHERE id=?");     $ne="\n[FARE BOOSTED] +Rs.$bf at ".date('h:i A'); $stmt->bind_param('dsi',$bf,$ne,$bid10); $stmt->execute();
    $bfB = dbRows('SELECT driver_id,status FROM app_bookings WHERE id=?','i',[$bid10]);
    if (empty($bfB[0]['driver_id']) && strtoupper(trim($bfB[0]['status'] ?? 'PENDING')) === 'PENDING') {
        $conn->query("UPDATE app_bookings SET driver_release_ends_at = DATE_ADD(NOW(), INTERVAL 2 MINUTE) WHERE id=$bid10");
    }
    broadcastRideLifecycleFCM('FARE_UPDATED',$bid10,['reason'=>'boost']); jsonResponse(['success'=>true,'message'=>"Fare boosted by Rs.$bf"]); }
if ($action === 'profile-update' && $method === 'POST') { if(empty($_SESSION['user'])) jsonResponse(['error'=>'Login required'],401); $nm=trim($b['name']??''); $em=trim($b['email']??''); if($nm) $_SESSION['user']['name']=$nm; if($em) $_SESSION['user']['email']=$em; jsonResponse(['success'=>true,'user'=>$_SESSION['user']]); }
