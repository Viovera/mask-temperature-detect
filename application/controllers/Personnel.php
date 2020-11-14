<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Personnel extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('session');
		$this->load->database(); //- load database
		$this->load->model('db_functions_model'); //- load database model

		$this->load->model('util_function_model'); //- load utilities model
		$this->util_function_model->check_valid_access('PERSONNEL'); //-check valid access

		$this->load->library('pagination'); //call pagination library
		$this->load->library('table'); //- library for generate table
		$this->load->helper('string');
		$this->load->helper('date');
		$this->load->helper('url');
		$this->load->library('excel'); //load our new PHPExcel library
		$this->load->library('Dynamic_menu');

		$this->output->set_header('Last-Modified: ' . gmdate("D, d M Y H:i:s") . ' GMT');
		$this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
		$this->output->set_header('Pragma: no-cache');
		$this->output->set_header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	}

	public function index($passive=0)
	{
		$data['base']			= $this->config->item('base_url');
		$data['images_folder']	= $this->config->item('images');
		$data['images_icon']	= $this->config->item('images_icon');
		$data['js']				= $this->config->item('js');
		$data['css']			= $this->config->item('css');
		$data['author']			= $this->config->item('author');
		$data['site_title']		= $this->config->item('site_title');

		if($passive==0){
			$data['page_heading']	= "Personnel";
		}
		else{
			$data['page_heading']	= "Passive Personnel";
		}

		$data['display_info']=$this->session->flashdata('msg_to_show');
		$admin_id=$this->session->userdata('admin_id');
		$admin_name=$this->session->userdata('admin_name');
		$contact_level=$this->session->userdata('admin_level');
		//========================================================

		$data['admin_name']=$admin_name;
		$data['passive']=$passive;

		//============ CHECK PRIV FOR SECURITY ========================
		if($passive==1){
			$val_read=$this->util_function_model->check_priv('PERSONNEL-VIEW-PASSIVE',$admin_id,'read_val'); //-check valid access for read
			$val_write=$this->util_function_model->check_priv('PERSONNEL-VIEW-PASSIVE',$admin_id,'write_val'); //-check valid access for write
		}
		else{
			$val_read=$this->util_function_model->check_priv('PERSONNEL-VIEW',$admin_id,'read_val'); //-check valid access for read
			$val_write=$this->util_function_model->check_priv('PERSONNEL-VIEW',$admin_id,'write_val'); //-check valid access for write
		}

		if($contact_level==1){
			$val_write=TRUE;
		}

		$data['val_write']=$val_write;

		isset($_POST["txt_card_no_s"]) ? $card_no_s = strip_slashes(strip_quotes($_POST["txt_card_no_s"])) : $card_no_s = '';

		//========= GENERATE DEPARTMENTS LISTING FOR EXPORT ================
		$data['cmb_dep_export']="<select id=\"select_dep_export\" name=\"select_dep_export\" data-placeholder=\"Select Department...\" class=\"chosen-select form-control\" style=\"width:200px;\">";
		$data['cmb_dep_export'].="<option value=\"0\">All...</option>";
		$ssql="
				select deptid,deptname from departments where supdeptid>0
				";
		$view_dep = $this->db->query($ssql);
		$table_data=$view_dep->result();
		if($table_data){
			foreach ($table_data as $row)
			{
				$deptid=$row->deptid;
				$deptname=$row->deptname;

				$data['cmb_dep_export'].="<option value=\"$deptid\">$deptname</option>";
			}
		}
		$data['cmb_dep_export'].="</select>";
		//========================================================

		/*generate tables*/
		$this->load->model('table_model'); //- load table model

		/**** styling the table ****/
		//- you can describe your own template here by use the variable of $tmpl_table
		$tmpl_table='';//- used default style
		$this->table_model->set_template($tmpl_table); //- set template
		if($passive==0){
			$this->table->set_heading( 'No.','Personnel ID','Name','Department','Street','Plate Number','Card No.','Vehicle Type','Note','Action'); //- create the header of table
		}
		else{
			$this->table->set_heading( 'No.','Personnel ID','Name','Street','Plate Number','Card No.','Vehicle Type','Note','Action'); //- create the header of table
		}

		$where='';
		if($card_no_s!=''){
			$where=" AND t1.cardno='$card_no_s'";
		}

		$row_no=1;
		if($passive==0){
			$sSQL="
				SELECT
					t1.userid,
					t1.badgenumber,
					t1.name,
					t1.plate_number,
					t1.cardno,
					t1.street,
					t1.vehicle_type,
					t1.note,
					t2.deptname
				FROM
					userinfo t1,departments t2
				WHERE
					t1.defaultdeptid=t2.deptid
				$where
				";
		}
		else{
			$datestring = "%Y-%m-%d";
			$time       = time();
			$curr_date  = mdate($datestring, $time);

		    $start_date=date("Y-m-d", strtotime("-1 month", strtotime(date("Y-m-d"))));
		    $end_date=$curr_date;

		    $sSQL="
				SELECT
					t1.userid,
					t1.badgenumber,
					t1.name,
					t1.plate_number,
					t1.cardno, t1.street,
					vehicle_type,
					note,
					'' as deptname
				FROM
					userinfo t1
					LEFT JOIN device_log t2
					ON t1.badgenumber=t2.pin
				WHERE
					t2.pin IS NULL
			";

		}
		$view_data = $this->db->query($sSQL);
		$table_data=$view_data->result();
		if($table_data){
			foreach ($table_data as $row)
			{
				$userid=$row->userid;
				$badgenumber=$row->badgenumber;//PIN
				$name=$row->name;//name
				$plate_number=$row->plate_number;//number plate
				$cardNo=$row->cardno;//card number
				$street=$row->street;//-blok rumah
				$vehicle_type=$row->vehicle_type;//-tipe kendaraan
				$note=$row->note;
				$deptname=$row->deptname;

				$icon_delete='';
				$icon_detail='';

				if($val_write){
					$icon_delete="<img src=\"".$data['images_icon']."/icons/delete.png\" title=\"Delete\" onclick=\"confirm_delete($userid,$badgenumber,'$cardNo')\">&nbsp;&nbsp;";
					$icon_detail="<img src=\"".$data['images_icon']."/icons/edit.png\" title=\"Edit\" id=\"btn_dialog\" onclick=\"javascript:window.location.assign('".$data['base']."/personnel/personnel_detail/$userid')\">&nbsp;&nbsp;";
				}
				$action_button="<div align=\"left\">$icon_detail$icon_delete</div>";

				if($vehicle_type==1){
					$vehicle_str='<span class="external-event bg-success">CAR</span>';
				}
				else{
					$vehicle_str='<span class="external-event bg-warning">MOTORCYCLE</span>';
				}

				if($passive==0){
				    $data_arr = array(
				    	$row_no.".",
				    	$badgenumber,
				    	$name,
				    	$deptname,
				    	$street,
				    	$plate_number,
				    	$cardNo,
				    	$vehicle_str,
				    	$note,
						$action_button //- action
		            );
				}
				else{
					$data_arr = array(
			    	$row_no.".",
			    	$badgenumber,
			    	$name,
			    	$street,
			    	$plate_number,
			    	$cardNo,
			    	$vehicle_str,
			    	$note,
					$action_button //- action
	            );
				}
			    $this->table->add_row($data_arr);
			    $row_no++;
			}

			$data['display_table']=$this->table->generate();
		}
		else{
			$data['display_table']=$this->util_function_model->info_msg("You don't have any data to show.");
		}

		$this->table->clear();
		/*end tables*/
		//========================================================

		$data['dyn_menu']=$this->load->view('templates/dyn_menu',$data,true);
		$this->load->view('templates/header',$data);
		$this->load->view('personnel/personnel',$data);
		$this->load->view('templates/footer');
	}

	public function personnel_fnl(){
		//- init all variables
		$base = $this->config->item('base_url');
		isset($_POST["proc_val"]) ? $proc_val=strip_slashes(strip_quotes($_POST["proc_val"])) : $proc_val="";
		isset($_POST["is_ajax"]) ? $is_ajax=strip_slashes(strip_quotes($_POST["is_ajax"])) : $is_ajax=0;
		isset($_POST["rid"]) ? $rid=strip_slashes(strip_quotes($_POST["rid"])) : $rid=0;

		$datestring = "%Y-%m-%d %H:%i:%s";
		$time = time();
		$curr_date= mdate($datestring, $time);

		$admin_id=$this->session->userdata('admin_id');

		isset($_POST["txt_personnel_id"]) ? $personnel_id=strip_slashes(strip_quotes($_POST["txt_personnel_id"])) : $personnel_id=0;
		isset($_POST["txt_name"]) ? $name=strip_slashes(strip_quotes($_POST["txt_name"])) : $name='';
		isset($_POST["txt_plate_number"]) ? $plate_number=strip_slashes(strip_quotes($_POST["txt_plate_number"])) : $plate_number='';
		isset($_POST["txt_street"]) ? $street=strip_slashes(strip_quotes($_POST["txt_street"])) : $street='';
		isset($_POST["txt_card_no"]) ? $card_no=strip_slashes(strip_quotes($_POST["txt_card_no"])) : $card_no='';
		isset($_POST["txt_card_no_ori"]) ? $card_no_ori=strip_slashes(strip_quotes($_POST["txt_card_no_ori"])) : $card_no_ori='';
		isset($_POST["select_dep"]) ? $select_dep=strip_slashes(strip_quotes($_POST["select_dep"])) : $select_dep=0;
		isset($_POST["txt_note"]) ? $note=strip_slashes(strip_quotes($_POST["txt_note"])) : $note='';
		isset($_POST["rb_vehicle_type"]) ? $rb_vehicle_type=strip_slashes(strip_quotes($_POST["rb_vehicle_type"])) : $rb_vehicle_type='';
		isset($_POST["txt_note"]) ? $note=strip_slashes(strip_quotes($_POST["txt_note"])) : $note='';
		isset($_POST["chk_date_period"]) ? $date_period=strip_slashes(strip_quotes($_POST["chk_date_period"])) : $date_period='';
		isset($_POST["txt_filter_date"]) ? $filter_date=strip_slashes(strip_quotes($_POST["txt_filter_date"])) : $filter_date='';

		$filter_date_split=explode(" - ", $filter_date);
		$start_date=$this->util_function_model->ddate2($filter_date_split[0]);
		$end_date=$this->util_function_model->ddate2($filter_date_split[1]);

		$personnel_id=trim($personnel_id);

		$access_level=array();
		isset($_POST["access_level"]) ? $access_level=strip_slashes(strip_quotes($_POST["access_level"])) : $access_level=0;

		$data_info=array();

		if($is_ajax==1){
			if($proc_val=="proc_new"){
				//-check valid card
				if($card_no!=''){
					$criteria='and reserved_status=0';
					$valid_card_no=$this->util_function_model->check_valid_card_no($card_no,$criteria);
					if($valid_card_no==''){
						$msg="Card No. $card_no is not authorized. Please choose another.";
						$url='';

			            array_push($data_info, $msg,$url);
			            echo json_encode($data_info);
			            die("");
					}
					else{
						if(!password_verify($card_no,$valid_card_no)){
							$msg="Card No. $card_no is not authorized. Please choose another.";
							$url='';

				            array_push($data_info, $msg,$url);
				            echo json_encode($data_info);
				            die("");
						}
					}
				}
				//======================================

				//-check valid personnel id
				$valid_personnel_id=$this->check_valid_id($personnel_id);
				if($valid_personnel_id){
					$msg="Personnel ID $personnel_id already exist. Please choose another.";
					$url='';

		            array_push($data_info, $msg,$url);
		            echo json_encode($data_info);
		            die("");
				}
				//======================================

				//-insert data personnel
				$valid_period=0;
				if($date_period=='on'){
					$valid_period=1;
				}

				$ssql="
						insert into userinfo (badgenumber,plate_number,name,street,cardno,defaultdeptid,vehicle_type,note,valid_period,start_time,end_time,create_by,create_time)
						values('$personnel_id','$plate_number','$name','$street','$card_no',$select_dep,$rb_vehicle_type,'$note',$valid_period,'$start_date','$end_date',$admin_id,'$curr_date')
					";
				$insert_data = $this->db->query($ssql);

				//=================================================
				//-get device ip
				$sSQL="SELECT device_ip FROM devices";
				$view_data = $this->db->query($sSQL);
				$table_data=$view_data->result();
				if($table_data){
					foreach ($table_data as $row)
					{
						$device_ip=$row->device_ip;


						//StartTime=EndTime=
						//-insert to bridge table
						if($date_period=='on'){
							$start_date=str_replace("-", "", $start_date);
							$end_date=str_replace("-", "", $end_date);
							$command="Pin=$personnel_id*CardNo=$card_no*StartTime=$start_date*EndTime=$end_date";
						}
						else{
							$command="Pin=$personnel_id*CardNo=$card_no";
						}
						$table="user";
						$this->util_function_model->insert_to_bridge($command,$table,$device_ip,'insert');
					}
				}
				//=================================================

				//-get userid of personnel
				$userid=0;
				$sSQL="SELECT userid FROM userinfo WHERE badgenumber='$personnel_id'";
				$view_data = $this->db->query($sSQL);
				$table_data=$view_data->result();
				if($table_data){
					foreach ($table_data as $row)
					{
						$userid=$row->userid;
					}
				}
				//=========================================

				//-insert to personnel_issuecard table
				if($card_no!=''){
					$ssql="
							insert into personnel_issuecard (create_by,create_time,userid_id,cardno)
							values($admin_id,'$curr_date',$personnel_id,'$card_no')
						";
					$insert_data = $this->db->query($ssql);
				}

				//-update reserved card table
				$ssql="
					update reserved_card set reserved_status=$userid, update_by=$admin_id,update_time='$curr_date' where card_no_ori='$card_no'
				";
				$update_data = $this->db->query($ssql);
				//=========================================

				//-insert data access level
				$tot_data_access=count($access_level);
				if($tot_data_access==1 && $access_level[0]==''){
					$tot_data_access=0;
				}


				for($a=0;$a<$tot_data_access;$a++){
					$acclevelset_id=$access_level[$a];

					$ssql="
						insert into acc_levelset_emp (acclevelset_id,employee_id)
						values($acclevelset_id,$userid)
					";
					$insert_data = $this->db->query($ssql);
					//==================

					/*
					$a=1<<(1-1);
					$b=1<<(2-1);
					$c=1<<(3-1);
					$d=1<<(4-1);

					$hasil=$a+$b+$c+$d;
					echo $hasil;
					die("");
					*/
					//-insert to bridge table
					$sql_auto = "select distinct(device_id) device_id from acc_levelset_door_group t1 where level_id=$acclevelset_id";
					$view_auto = $this->db->query($sql_auto);
					$table_data_auto=$view_auto->result();
					if($table_data_auto){
						foreach ($table_data_auto as $row_auto){
							$device_id=$row_auto->device_id;

							$AuthorizeDoorId=0;
							$sql = "select door_no, (select device_ip from devices t2 where t1.device_id=t2.device_id) device_ip from acc_levelset_door_group t1 where level_id=$acclevelset_id and device_id=$device_id";
							$view_user = $this->db->query($sql);
							$table_data=$view_user->result();
							if($table_data){
								foreach ($table_data as $row){
									$accdoor_id=$row->door_no;
									$device_ip=$row->device_ip;

									$cek_door=1<<($accdoor_id-1);
									$AuthorizeDoorId=$AuthorizeDoorId+$cek_door;
								}
							}

							$command="Pin=$personnel_id*AuthorizeTimezoneId=1*AuthorizeDoorId=".$AuthorizeDoorId;
							$table="userauthorize";
							$this->util_function_model->insert_to_bridge($command,$table,$device_ip,'insert');
						}
					}
				}
				//=========================================

				//-proses ke controller
				echo exec('C:\VMS\VMSSet.exe');

		        $msg="Operation successful! New Personnel has been added";
				$url=$base.'/personnel';

				$status_msg="Operation successful! New Personnel has been added";
				$msg_to_show=$this->util_function_model->info_msg($status_msg);
		        $this->session->set_flashdata('msg_to_show', $msg_to_show);

		        //==== INSERT TO ACTION LOG ===========================
				$this->util_function_model->insert_to_action_log($admin_id,'PERSONNEL',$proc_val);
				//=====================================================

	            array_push($data_info, $msg,$url);
	            echo json_encode($data_info);
	            die("");
			}

			if($proc_val=="proc_edit"){
				//-check valid card
				if(($card_no_ori!=$card_no) && ($card_no!='')){
					$criteria='and reserved_status=0';
					$valid_card_no=$this->util_function_model->check_valid_card_no($card_no,$criteria);

					if($valid_card_no==''){
						$msg="Card No. $card_no is not authorized. Please choose another.";
						$url='';

			            array_push($data_info, $msg,$url);
			            echo json_encode($data_info);
			            die("");
					}
					else{
						if(!password_verify($card_no,$valid_card_no)){
							$msg="Card No. $card_no is not authorized. Please choose another.";
							$url='';

				            array_push($data_info, $msg,$url);
				            echo json_encode($data_info);
				            die("");
						}
					}
				}
				//======================================

				//-update data personnel
				$ssql="
						update userinfo set
						badgenumber='$personnel_id',plate_number='$plate_number',name='$name',
						street='$street',cardno='$card_no',defaultdeptid=$select_dep,note='$note',vehicle_type=$rb_vehicle_type,
						update_by=$admin_id,update_time='$curr_date'
						where userid=$rid
					";
				$update_data = $this->db->query($ssql);

				//=================================================
				//-get device ip
				$sSQL="SELECT device_ip FROM devices";
				$view_data = $this->db->query($sSQL);
				$table_data=$view_data->result();
				if($table_data){
					foreach ($table_data as $row)
					{
						$device_ip=$row->device_ip;

						//-insert to bridge table
						if($date_period=='on'){
							$start_date=str_replace("-", "", $start_date);
							$end_date=str_replace("-", "", $end_date);
							$command="Pin=$personnel_id*CardNo=$card_no*StartTime=$start_date*EndTime=$end_date";
						}
						else{
							$command="Pin=$personnel_id*CardNo=$card_no";
						}
						$table="user";
						$this->util_function_model->insert_to_bridge($command,$table,$device_ip,'insert');
					}
				}
				//=================================================

				//-get userid of personnel
				$userid=0;
				$sSQL="SELECT userid FROM userinfo WHERE badgenumber='$personnel_id'";
				$view_data = $this->db->query($sSQL);
				$table_data=$view_data->result();
				if($table_data){
					foreach ($table_data as $row)
					{
						$userid=$row->userid;
					}
				}
				//=========================================

				//-delete to personnel_issuecard table
				//-jangan di delete biar bisa tahu kartu pernah di kasih ke siapa aja
				/*
				$ssql="
						delete personnel_issuecard from personnel_issuecard
						where userid_id=$personnel_id and cardno='$card_no'
					";
				$delete_data = $this->db->query($ssql);
				*/
				if($card_no!=''){
					$ssql="
							insert into personnel_issuecard (create_by,create_time,userid_id,cardno)
							values($admin_id,'$curr_date',$personnel_id,'$card_no')
						";
					$insert_data = $this->db->query($ssql);
				}

				//-update reserved card table
				$ssql="
					update reserved_card set reserved_status=$userid, update_by=$admin_id,update_time='$curr_date' where card_no_ori='$card_no'
				";

				$update_data = $this->db->query($ssql);

				if(($card_no_ori!=$card_no)){
					$ssql="
						update reserved_card set reserved_status=0, update_by=$admin_id,update_time='$curr_date' where card_no_ori='$card_no_ori'
					";
					$update_data = $this->db->query($ssql);
				}
				//=========================================

				//-insert data access level
				$tot_data_access=count($access_level);
				if($tot_data_access==1 && $access_level[0]==''){
					$tot_data_access=0;
				}

				$ssql="
						delete acc_levelset_emp from acc_levelset_emp
						where employee_id=$userid
					";
				$delete_data = $this->db->query($ssql);

				for($a=0;$a<$tot_data_access;$a++){
					$acclevelset_id=$access_level[$a];

					$ssql="
						insert into acc_levelset_emp (acclevelset_id,employee_id)
						values($acclevelset_id,$userid)
					";
					$insert_data = $this->db->query($ssql);

					//-insert to bridge table
					$sql_auto = "select distinct(device_id) device_id from acc_levelset_door_group t1 where level_id=$acclevelset_id";
					$view_auto = $this->db->query($sql_auto);
					$table_data_auto=$view_auto->result();
					if($table_data_auto){
						foreach ($table_data_auto as $row_auto){
							$device_id=$row_auto->device_id;

							$AuthorizeDoorId=0;
							$sql = "select door_no, (select device_ip from devices t2 where t1.device_id=t2.device_id) device_ip from acc_levelset_door_group t1 where level_id=$acclevelset_id and device_id=$device_id";
							$view_user = $this->db->query($sql);
							$table_data=$view_user->result();
							if($table_data){
								foreach ($table_data as $row){
									$accdoor_id=$row->door_no;
									$device_ip=$row->device_ip;

									$cek_door=1<<($accdoor_id-1);
									$AuthorizeDoorId=$AuthorizeDoorId+$cek_door;
								}
							}

							$command="Pin=$personnel_id*AuthorizeTimezoneId=1*AuthorizeDoorId=".$AuthorizeDoorId;
							$table="userauthorize";
							$this->util_function_model->insert_to_bridge($command,$table,$device_ip,'update');
						}
					}
				}

				if($tot_data_access==0){
					$command="Pin=$personnel_id*AuthorizeTimezoneId=1*AuthorizeDoorId=0";
					$table="userauthorize";
					$this->util_function_model->insert_to_bridge($command,$table,$device_ip,'update');
				}
				//=========================================

				//-proses ke controller
				echo exec('C:\VMS\VMSSet.exe');

		        $msg="Operation successful! Personnel has been updated";
				$url=$base.'/personnel';

				$status_msg="Operation successful! Personnel has been updated";
				$msg_to_show=$this->util_function_model->info_msg($status_msg);
		        $this->session->set_flashdata('msg_to_show', $msg_to_show);

		        //==== INSERT TO ACTION LOG ===========================
				$this->util_function_model->insert_to_action_log($admin_id,'PERSONNEL',$proc_val);
				//=====================================================

	            array_push($data_info, $msg,$url);
	            echo json_encode($data_info);
	            die("");
			}
		}

		if($proc_val=="proc_delete"){
			$ssql="
				delete userinfo from userinfo
				where userid=$rid
			";
			$delete_user = $this->db->query($ssql);
			//=================================================
			$ssql="
				delete personnel_issuecard from personnel_issuecard
				where userid_id=$rid
			";
			$delete_user = $this->db->query($ssql);
			//=================================================
			$ssql="
				update reserved_card set reserved_status=0, update_by=$admin_id,update_time='$curr_date' where reserved_status=$rid
			";
			$update_data = $this->db->query($ssql);
			//=================================================

			//-get device ip
			$sSQL="SELECT device_ip FROM devices";
			$view_data = $this->db->query($sSQL);
			$table_data=$view_data->result();
			if($table_data){
				foreach ($table_data as $row)
				{
					$device_ip=$row->device_ip;

					//-insert to bridge table
					$command="Pin=$personnel_id*CardNo=$card_no";
					$table="user";
					$this->util_function_model->insert_to_bridge($command,$table,$device_ip,'delete');
				}
			}
			//=================================================

			$access_level=array();
			$tot_data_access=0;
			$sSQL="SELECT acclevelset_id FROM acc_levelset_emp where employee_id=$rid";
			$view_data = $this->db->query($sSQL);
			$table_data=$view_data->result();
			if($table_data){
				foreach ($table_data as $row)
				{
					$access_level[$tot_data_access]=$row->acclevelset_id;
					$tot_data_access++;
				}
			}

			for($a=0;$a<$tot_data_access;$a++){
				$acclevelset_id=$access_level[$a];

				//-insert to bridge table
				$sql_auto = "select distinct(device_id) device_id from acc_levelset_door_group t1 where level_id=$acclevelset_id";
				$view_auto = $this->db->query($sql_auto);
				$table_data_auto=$view_auto->result();
				if($table_data_auto){
					foreach ($table_data_auto as $row_auto){
						$device_id=$row_auto->device_id;

						$AuthorizeDoorId=0;
						$sql = "select door_no, (select device_ip from devices t2 where t1.device_id=t2.device_id) device_ip from acc_levelset_door_group t1 where level_id=$acclevelset_id and device_id=$device_id";
						$view_user = $this->db->query($sql);
						$table_data=$view_user->result();
						if($table_data){
							foreach ($table_data as $row){
								$accdoor_id=$row->door_no;
								$device_ip=$row->device_ip;

								$cek_door=1<<($accdoor_id-1);
								$AuthorizeDoorId=$AuthorizeDoorId+$cek_door;
							}
						}

						$command="Pin=$personnel_id*AuthorizeTimezoneId=1*AuthorizeDoorId=".$AuthorizeDoorId;
						$table="userauthorize";
						$this->util_function_model->insert_to_bridge($command,$table,$device_ip,'delete');
					}
				}
			}

			$ssql="
				delete acc_levelset_emp from acc_levelset_emp
				where employee_id=$rid
			";
			$delete_user = $this->db->query($ssql);
			//=================================================

			//==== INSERT TO ACTION LOG ===========================
			$this->util_function_model->insert_to_action_log($admin_id,'PERSONNEL',$proc_val);
			//=====================================================

			//-proses ke controller
			echo exec('C:\VMS\VMSSet.exe');

			$status_msg="Operation successful! Personnel ID $personnel_id has been deleted";
			$msg_to_show=$this->util_function_model->info_msg($status_msg);
	        $this->session->set_flashdata('msg_to_show', $msg_to_show);
	        redirect('personnel', 'refresh');
		}
	}

	public function personnel_detail($rid){
		$data['base']			= $this->config->item('base_url');
		$data['images_folder']	= $this->config->item('images');
		$data['js']				= $this->config->item('js');
		$data['css']			= $this->config->item('css');
		$data['author']			= $this->config->item('author');
		$data['site_title']		= $this->config->item('site_title');
		$data['page_heading']	= "Edit Personnel";

		$data['val']="edit";
		$data['rid']=$rid;
		$data['card_no']="";
		$data['note']='';

		$admin_id=$this->session->userdata('admin_id');
		$admin_name=$this->session->userdata('admin_name');
		$contact_level=$this->session->userdata('admin_level');
		//========================================================

		$data['admin_name']=$admin_name;
		$data['contact_level']=$contact_level;
		$data['priv_edit']=false;
		if($this->util_function_model->check_priv('PERSONNEL',$admin_id)){//- check privilege for edit user
			$data['priv_edit']=true;
		}

		$sql="
				SELECT
					userid,badgenumber,plate_number,name,street,cardno,defaultdeptid,vehicle_type,note,
					valid_period,date(start_time) start_time,date(end_time) end_time
				FROM
					userinfo t1
				WHERE
					userid=$rid
			";
		$view_user = $this->db->query($sql);
		$table_data=$view_user->result();
		if($table_data){
			foreach ($table_data as $row){
				$data['personnel_id']=$row->badgenumber;
				$data['personnel_name']=$row->name;
				$data['plate_number']=$row->plate_number;
				$data['personnel_street']=$row->street;
				$data['card_no']=$row->cardno;
				$data['vehicle_type']=$row->vehicle_type;
				$dep_id=$row->defaultdeptid;
				$data['note']=$row->note;
				$data['valid_period']=$row->valid_period;

				$start_time=$row->start_time;

				if($start_time=='0000-00-00'){
					$datestring = "%d-%m-%Y";
					$time = time();
					$curr_date= mdate($datestring, $time);

					$end_date=$curr_date;
					$start_date=$curr_date;
				}
				else{
					$start_date=$this->util_function_model->ddate2($row->start_time);
					$end_date=$this->util_function_model->ddate2($row->end_time);
				}
				$data['date_periode']=$start_date.' - '.$end_date;
			}
		}

		//========= GENERATE DEPARTMENT LISTING ================
		$data['cmb_dep']="<select id=\"select_dep\" name=\"select_dep\" data-placeholder=\"Select Department...\" class=\"chosen-select\" style=\"width:200px;\">";
		$ssql="
				select deptid,deptname from departments
				";
		$view_dep = $this->db->query($ssql);
		$table_data=$view_dep->result();
		if($table_data){
			foreach ($table_data as $row)
			{
				$deptid=$row->deptid;
				$deptname=$row->deptname;
				if($deptid==$dep_id){
					$data['cmb_dep'].="<option value=\"$deptid\" selected=\"selected\">$deptname</option>";
				}
				else{
					$data['cmb_dep'].="<option value=\"$deptid\">$deptname</option>";
				}
			}
		}
		$data['cmb_dep'].="</select>";
		//========================================================

		//========= GENERATE ACCESS LEVEL LISTING ================
		$data['cmb_access_level']="<select id=\"access_level[]\" multiple name=\"access_level[]\" data-placeholder=\"Select Access Level...\" class=\"chosen-select\" style=\"width:200px;\">";
		$ssql="
				select id,level_name from acc_levelset
				";
		$view_dep = $this->db->query($ssql);
		$table_data=$view_dep->result();
		if($table_data){
			foreach ($table_data as $row)
			{
				$level_id=$row->id;
				$level_name=$row->level_name;

				$tot_level=0;
				$query_level="
					select count(id) as tot_level from acc_levelset_emp where employee_id=$rid and acclevelset_id=$level_id
					";
				//die("");
				$view_level = $this->db->query($query_level);
				$level_data=$view_level->result();
				if($level_data){
					foreach ($level_data as $row_level)
					{
						$tot_level=$row_level->tot_level;
					}
				}

				if($tot_level>0){
					$data['cmb_access_level'].="<option value=\"$level_id\" selected=\"selected\">$level_name</option>";
				}
				else{
					$data['cmb_access_level'].="<option value=\"$level_id\">$level_name</option>";
				}
			}
		}
		$data['cmb_access_level'].="</select>";
		//========================================================

		$data['dyn_menu']=$this->load->view('templates/dyn_menu',$data,true);
		$this->load->view('templates/header',$data);
		$this->load->view('personnel/personnel_tab',$data);
		$this->load->view('templates/footer');
	}

	public function personnel_new(){
		$data['base']			= $this->config->item('base_url');
		$data['images_folder']	= $this->config->item('images');
		$data['js']				= $this->config->item('js');
		$data['css']			= $this->config->item('css');
		$data['author']			= $this->config->item('author');
		$data['site_title']		= $this->config->item('site_title');
		$data['page_heading']	= "Add New Personnel";

		$data['val']="new";

		$admin_id=$this->session->userdata('admin_id');
		$admin_name=$this->session->userdata('admin_name');
		$contact_level=$this->session->userdata('admin_level');
		//========================================================

		$data['admin_name']=$admin_name;

		$data['priv_edit']=false;
		if($this->util_function_model->check_priv('PERSONNEL',$admin_id)){//- check privilege for edit user
			$data['priv_edit']=true;
		}

		$data['rid']='';
		$data['personnel_id']='';
		$data['personnel_name']='';
		$data['plate_number']='';
		$data['personnel_street']='';
		$data['card_no']='';
		$data['vehicle_type']=1;
		$data['note']='';
		$data['valid_period']=0;

		$datestring = "%d-%m-%Y";
		$time = time();
		$curr_date= mdate($datestring, $time);

		$end_date=$curr_date;
		$start_date=$curr_date;
		$data['date_periode']=$start_date.' - '.$end_date;

		//========= GENERATE DEPARTMENT LISTING ================
		$data['cmb_dep']="<select id=\"select_dep\" name=\"select_dep\" data-placeholder=\"Select Department...\" class=\"chosen-select\" style=\"width:200px;\">";
		$ssql="
				select deptid,deptname from departments
				";
		$view_dep = $this->db->query($ssql);
		$table_data=$view_dep->result();
		if($table_data){
			foreach ($table_data as $row)
			{
				$deptid=$row->deptid;
				$deptname=$row->deptname;
				$data['cmb_dep'].="<option value=\"$deptid\">$deptname</option>";
			}
		}
		$data['cmb_dep'].="</select>";
		//========================================================

		//========= GENERATE ACCESS LEVEL LISTING ================
		$data['cmb_access_level']="<select id=\"access_level[]\" multiple name=\"access_level[]\" data-placeholder=\"Select Access Level...\" class=\"chosen-select\" style=\"width:200px;\">";
		$ssql="
				select id,level_name from acc_levelset
				";
		$view_dep = $this->db->query($ssql);
		$table_data=$view_dep->result();
		if($table_data){
			foreach ($table_data as $row)
			{
				$id=$row->id;
				$level_name=$row->level_name;
				$data['cmb_access_level'].="<option value=\"$id\">$level_name</option>";
			}
		}
		$data['cmb_access_level'].="</select>";
		//========================================================

		$data['dyn_menu']=$this->load->view('templates/dyn_menu',$data,true);
		$this->load->view('templates/header',$data);
		$this->load->view('personnel/personnel_tab',$data);
		$this->load->view('templates/footer');
	}

	public function personnel_to_excel(){
		isset($_POST["select_dep_export"]) ? $select_dep = strip_slashes(strip_quotes($_POST["select_dep_export"])):$select_dep = 0;

		$admin_id=$this->session->userdata('admin_id');
		$admin_name=$this->session->userdata('admin_name');
		$contact_level=$this->session->userdata('admin_level');
		//========================================================
		$base = $this->config->item('base_url');

		//============ CHECK PRIV FOR SECURITY ========================
		$val_read=$this->util_function_model->check_priv('PERSONNEL-VIEW',$admin_id,'read_val'); //-check valid access for read
		$val_write=$this->util_function_model->check_priv('PERSONNEL-VIEW',$admin_id,'write_val'); //-check valid access for write
		if($contact_level==1){
			$val_write=TRUE;
		}

		$this->excel->setActiveSheetIndex(0);
		//name the worksheet
		$this->excel->getActiveSheet()->setTitle('Personnel');

		//=========== START HEADER =========================
		$comp_name = $this->config->item('author');
		$headerText = $comp_name;
		$titleText = 'Personnel';
		$footnote='Generated : ' . date('d M Y');

		$this->excel->getActiveSheet()->setCellValue('A1', $headerText);
		$this->excel->getActiveSheet()->setCellValue('A2', $titleText);
		$this->excel->getActiveSheet()->setCellValue('A3', $footnote);

		//change the font size
		$this->excel->getActiveSheet()->getStyle('A1')->getFont()->setSize(14);
		$this->excel->getActiveSheet()->getStyle('A2')->getFont()->setSize(12);
		$this->excel->getActiveSheet()->getStyle('A3')->getFont()->setSize(10);
		//make the font become bold
		$this->excel->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);
		$this->excel->getActiveSheet()->getStyle('A3:J3')->getBorders()->getBottom()->setBorderStyle(PHPExcel_Style_Border::BORDER_THICK);

		//merge cell A1 until H1
		$this->excel->getActiveSheet()->mergeCells('A1:J1');
		$this->excel->getActiveSheet()->mergeCells('A2:J2');
		$this->excel->getActiveSheet()->mergeCells('A3:J3');
		//set aligment to center for that merged cell (A1 to D1)
		$this->excel->getActiveSheet()->getStyle('A1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
		$this->excel->getActiveSheet()->getStyle('A2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
		$this->excel->getActiveSheet()->getStyle('A3')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

		//================= END OF HEADER ==================================================================================================

		// An array with the data for the column headings
		$colNames = array( 'No.','Personnel ID','Name','Department','Street','Plate Number','Card No.','Vehicle Type','Note'); //- create the header of table
		$this->excel->getActiveSheet()->fromArray($colNames,NULL,'A5');
		$this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(5);
		$this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
		$this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
		$this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
		$this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
		$this->excel->getActiveSheet()->getColumnDimension('F')->setWidth(20);
		$this->excel->getActiveSheet()->getColumnDimension('G')->setWidth(20);
		$this->excel->getActiveSheet()->getColumnDimension('H')->setWidth(20);
		$this->excel->getActiveSheet()->getColumnDimension('I')->setWidth(20);

		//============ SELECT FROM DATABASE ======================
		$query="
			t1.userid,
			t1.badgenumber,
			t1.name,
			t1.plate_number,
			t1.cardno,
			t1.street,
			t1.vehicle_type,
			t1.note,
			t2.deptname
			";
		$table_name="userinfo t1,departments t2";
		$this->db->where('t1.defaultdeptid=t2.deptid');
		if($select_dep>0){
			$this->db->where('t2.supdeptid>0');
			$this->db->where('(t2.deptid = '.$select_dep.' OR t2.supdeptid = '.$select_dep.')');
		}
		$this->db->select($query);
		$this->db->from($table_name);
		$this->db->group_by('t1.userid');
		$query = $this->db->get();
		$table_data=$query->result();
		//- displaying data
		$row_no=1;
		$currentRow=6;
		if($table_data){
			foreach ($table_data as $row)
			{
				$userid=$row->userid;
				$badgenumber=$row->badgenumber;//PIN
				$name=$row->name;//name
				$plate_number=$row->plate_number;//number plate
				$cardNo=$row->cardno;//card number
				$street=$row->street;//-blok rumah
				$vehicle_type=$row->vehicle_type;//-tipe kendaraan
				$note=$row->note;
				$deptname=$row->deptname;

				if($vehicle_type==1){
					$vehicle_str='CAR';
				}
				else{
					$vehicle_str='MOTORCYCLE';
				}

				$this->excel->getActiveSheet()->setCellValue('A'.$currentRow, $row_no);
				$this->excel->getActiveSheet()->setCellValue('B'.$currentRow, $badgenumber);
				$this->excel->getActiveSheet()->setCellValue('C'.$currentRow, $name);
				$this->excel->getActiveSheet()->setCellValue('D'.$currentRow, $deptname);
				$this->excel->getActiveSheet()->setCellValue('E'.$currentRow, $street);
				$this->excel->getActiveSheet()->setCellValue('F'.$currentRow, $plate_number);
				$this->excel->getActiveSheet()->setCellValue('G'.$currentRow, $cardNo);
				$this->excel->getActiveSheet()->setCellValue('H'.$currentRow, $vehicle_str);
				$this->excel->getActiveSheet()->setCellValue('I'.$currentRow, $note);

				$row_no++;
				$currentRow++;
			}
		}
		else{
			$status_msg="Data not found";
			$success_msg = "<script language=\"javascript\">
								alert('".$status_msg."');
								location.replace('".$base."/personnel');
							</script>";
			echo $success_msg;
			die("");
		}

		$datestring = "%d%m%Y_%H%i%s";
		$time = time();
		$today= mdate($datestring, $time);

		$filename='personnel_'.$today.'.xlsx'; //save our workbook as this file name
		header('Content-Type: application/vnd.ms-excel'); //mime type
		header('Content-Disposition: attachment;filename="'.$filename.'"'); //tell browser what's the file name
		header('Cache-Control: max-age=0'); //no cache


		//save it to Excel5 format (excel 2003 .XLS file), change this to 'Excel2007' (and adjust the filename extension, also the header mime type)
		//if you want to save it as .XLSX Excel 2007 format
		$objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel2007');
		ob_end_clean();
		//force user to download the Excel file without writing it to server's HD
		$objWriter->save('php://output');
	}

	public function department(){
		$data['base']			= $this->config->item('base_url');
		$data['images_folder']	= $this->config->item('images');
		$data['images_icon']	= $this->config->item('images_icon');
		$data['js']				= $this->config->item('js');
		$data['css']			= $this->config->item('css');
		$data['author']			= $this->config->item('author');
		$data['site_title']		= $this->config->item('site_title');
		$data['page_heading']	= "Personnel - Departments";

		$data['display_info']=$this->session->flashdata('msg_to_show');
		$admin_id=$this->session->userdata('admin_id');
		$admin_name=$this->session->userdata('admin_name');
		$contact_level=$this->session->userdata('admin_level');
		//========================================================

		$data['admin_name']=$admin_name;

		//============ CHECK PRIV FOR SECURITY ========================
		$val_read=$this->util_function_model->check_priv('PERSONNEL-DEP',$admin_id,'read_val'); //-check valid access for read
		$val_write=$this->util_function_model->check_priv('PERSONNEL-DEP',$admin_id,'write_val'); //-check valid access for write
		if($contact_level==1){
			$val_write=TRUE;
		}

		$data['val_write']=$val_write;
		$where="";

		/*generate tables*/
		$this->load->model('table_model'); //- load table model

		/**** styling the table ****/
		//- you can describe your own template here by use the variable of $tmpl_table
		$tmpl_table='';//- used default style
		$this->table_model->set_template($tmpl_table); //- set template
		$this->table->set_heading( 'No.','Department Name','Parent Department','Action'); //- create the header of table

		$row_no=1;
		$sSQL="
			SELECT
				t1.deptid,t1.deptname,t1.supdeptid,
				(select t2.deptname from departments t2 where t1.supdeptid=t2.deptid) as parentdep
			FROM
				departments t1
			$where
			";
		$view_dep = $this->db->query($sSQL);
		$table_data=$view_dep->result();
		if($table_data){
			foreach ($table_data as $row)
			{
				$deptid=$row->deptid;
				$deptname=$row->deptname;
				$supdeptid=$row->supdeptid;
				$parentdep=$row->parentdep;

				$icon_delete='';
				$icon_detail='';

				if($val_write){
					$icon_delete="<img src=\"".$data['images_icon']."/icons/delete.png\" title=\"Delete\" onclick=\"confirm_delete($deptid,'$deptname')\">&nbsp;&nbsp;";
					$icon_detail="<img src=\"".$data['images_icon']."/icons/edit.png\" title=\"Edit\" id=\"btn_dialog\" onclick=\"javascript:window.location.assign('".$data['base']."/personnel/department_detail/$deptid')\">&nbsp;&nbsp;";
				}
				$action_button="<div align=\"left\">$icon_detail$icon_delete</div>";

			    $data_arr = array(
			    	$row_no.".",
			    	$deptname,
			    	$parentdep,
					$action_button //- action
	            );
			    $this->table->add_row($data_arr);
			    $row_no++;
			}

			$data['display_table']=$this->table->generate();
		}
		else{
			$data['display_table']=$this->util_function_model->info_msg("You don't have any data to show.");
		}

		$this->table->clear();
		/*end tables*/
		//========================================================

		$data['dyn_menu']=$this->load->view('templates/dyn_menu',$data,true);
		$this->load->view('templates/header',$data);
		$this->load->view('personnel/department',$data);
		$this->load->view('templates/footer');
	}

	public function department_detail($rid){
		$data['base']			= $this->config->item('base_url');
		$data['images_folder']	= $this->config->item('images');
		$data['js']				= $this->config->item('js');
		$data['css']			= $this->config->item('css');
		$data['author']			= $this->config->item('author');
		$data['site_title']		= $this->config->item('site_title');
		$data['page_heading']	= "Edit Department";

		$data['val']="edit";
		$data['rid']=$rid;
		$data['card_no']="";

		$admin_id=$this->session->userdata('admin_id');
		$admin_name=$this->session->userdata('admin_name');
		$contact_level=$this->session->userdata('admin_level');
		//========================================================

		$data['admin_name']=$admin_name;
		$data['contact_level']=$contact_level;
		$data['priv_edit']=false;
		if($this->util_function_model->check_priv('PERSONNEL-DEP',$admin_id)){//- check privilege for edit user
			$data['priv_edit']=true;
		}

		$sql="
				SELECT
					deptid,
					deptname,
					supdeptid,
					code
				FROM
					departments t1
				WHERE
					deptid=$rid
			";

		$view_data = $this->db->query($sql);
		$table_data=$view_data->result();
		if($table_data){
			foreach ($table_data as $row){
				$data['deptid']=$row->deptid;
				$data['dep_name']=$row->deptname;
				$data['supdeptid']=$row->supdeptid;
				$data['dep_no']=$row->code;
			}
		}

		//========= GENERATE PARENT DEPARTMENT LISTING ================
		$data['cmb_parent_dep']="<select id=\"select_parent_dep\" name=\"select_parent_dep\" data-placeholder=\"Select Parent Department...\" class=\"chosen-select\" style=\"width:200px;\">";
		$data['cmb_parent_dep'].="<option value=\"0\">Top Department</option>";
		$ssql="
				select deptid,deptname from departments
				";
		$view_dep = $this->db->query($ssql);
		$table_data=$view_dep->result();
		if($table_data){
			foreach ($table_data as $row)
			{
				$deptid=$row->deptid;
				$deptname=$row->deptname;
				if($data['supdeptid']==$deptid){
					$data['cmb_parent_dep'].="<option value=\"$deptid\" selected=\"selected\">$deptname</option>";
				}
				else{
					$data['cmb_parent_dep'].="<option value=\"$deptid\">$deptname</option>";
				}
			}
		}

		$data['cmb_parent_dep'].="</select>";
		//========================================================

		$data['dyn_menu']=$this->load->view('templates/dyn_menu',$data,true);
		$this->load->view('templates/header',$data);
		$this->load->view('personnel/department_tab',$data);
		$this->load->view('templates/footer');
	}

	public function department_new(){
		$data['base']			= $this->config->item('base_url');
		$data['images_folder']	= $this->config->item('images');
		$data['js']				= $this->config->item('js');
		$data['css']			= $this->config->item('css');
		$data['author']			= $this->config->item('author');
		$data['site_title']		= $this->config->item('site_title');
		$data['page_heading']	= "Add New Department";

		$data['val']="new";

		$admin_id=$this->session->userdata('admin_id');
		$admin_name=$this->session->userdata('admin_name');
		$contact_level=$this->session->userdata('admin_level');
		//========================================================

		$data['admin_name']=$admin_name;

		$data['priv_edit']=false;
		if($this->util_function_model->check_priv('PERSONNEL-DEP',$admin_id)){//- check privilege for edit user
			$data['priv_edit']=true;
		}

		$data['rid']=0;
		$data['dep_name']='';
		$data['dep_no']='';

		//========= GENERATE PARENT DEPARTMENT LISTING ================
		$data['cmb_parent_dep']="<select id=\"select_parent_dep\" name=\"select_parent_dep\" data-placeholder=\"Select Parent Department...\" class=\"chosen-select\" style=\"width:200px;\">";
		$data['cmb_parent_dep'].="<option value=\"0\">Top Department</option>";
		$ssql="
				select deptid,deptname from departments
				";
		$view_dep = $this->db->query($ssql);
		$table_data=$view_dep->result();
		if($table_data){
			foreach ($table_data as $row)
			{
				$deptid=$row->deptid;
				$deptname=$row->deptname;
				$data['cmb_parent_dep'].="<option value=\"$deptid\">$deptname</option>";
			}
		}
		$data['cmb_parent_dep'].="</select>";
		//========================================================

		$data['dyn_menu']=$this->load->view('templates/dyn_menu',$data,true);
		$this->load->view('templates/header',$data);
		$this->load->view('personnel/department_tab',$data);
		$this->load->view('templates/footer');
	}

	public function department_fnl(){
		//- init all variables
		$base = $this->config->item('base_url');
		isset($_POST["proc_val"]) ? $proc_val=strip_slashes(strip_quotes($_POST["proc_val"])) : $proc_val="";
		isset($_POST["rid"]) ? $rid=strip_slashes(strip_quotes($_POST["rid"])) : $rid=0;

		$datestring = "%Y-%m-%d %H:%i:%s";
		$time = time();
		$curr_date= mdate($datestring, $time);

		$admin_id=$this->session->userdata('admin_id');

		isset($_POST["txt_dep_name"]) ? $dep_name=strip_slashes(strip_quotes($_POST["txt_dep_name"])) : $dep_name="";
		isset($_POST["txt_dep_number"]) ? $dep_number=strip_slashes(strip_quotes($_POST["txt_dep_number"])) : $dep_number="";
		isset($_POST["select_parent_dep"]) ? $parent_dep=strip_slashes(strip_quotes($_POST["select_parent_dep"])) : $parent_dep=0;
		isset($_POST["is_ajax"]) ? $is_ajax=strip_slashes(strip_quotes($_POST["is_ajax"])) : $is_ajax=0;

		$data_info=array();

		if($is_ajax==1){
			if($proc_val=="proc_new"){
				$str="deptname='$dep_name'";
				$valid_dep_name=$this->check_valid_dep($str);
				if($valid_dep_name){
					$msg="Department name $dep_name already exist. Please choose another.";
					$url='';

		            array_push($data_info, $msg,$url);
		            echo json_encode($data_info);
		            die("");
				}

				$ssql="
						insert into departments (deptname,supdeptid,code,create_by,create_time)
						values('$dep_name',$parent_dep,'$dep_number',$admin_id,'$curr_date')
					";
				$insert_data = $this->db->query($ssql);

		        $msg="Operation successful! New Department has been added";
				$url=$base.'/personnel/department';

				//==== INSERT TO ACTION LOG ===========================
				$this->util_function_model->insert_to_action_log($admin_id,'PERSONNEL-DEP',$proc_val);
				//=====================================================

	            array_push($data_info, $msg,$url);
	            echo json_encode($data_info);
	            die("");
			}

			if($proc_val=="proc_edit"){
				$ssql="
					update departments set
					deptname='$dep_name',supdeptid=$parent_dep,code='$dep_number',update_by=$admin_id,update_time='$curr_date'
					where deptid=$rid
				";
				$update_data = $this->db->query($ssql);

				$msg="Operation successful! Department has been updated";
				$url=$base.'/personnel/department';

	            array_push($data_info, $msg,$url);
	            echo json_encode($data_info);

	            //==== INSERT TO ACTION LOG ===========================
				$this->util_function_model->insert_to_action_log($admin_id,'PERSONNEL-DEP',$proc_val);
				//=====================================================

	            $status_msg="Operation successful! Department has been updated";
				$msg_to_show=$this->util_function_model->info_msg($status_msg);
		        $this->session->set_flashdata('msg_to_show', $msg_to_show);
	            die("");
			}
		}

		if($proc_val=="proc_delete"){
			$ssql="
				delete departments from departments
				where deptid=$rid
			";
			$delete_data = $this->db->query($ssql);

			//==== INSERT TO ACTION LOG ===========================
			$this->util_function_model->insert_to_action_log($admin_id,'PERSONNEL-DEP',$proc_val);
			//=====================================================

			$status_msg="Operation successful! Department $dep_name has been deleted";
			$msg_to_show=$this->util_function_model->info_msg($status_msg);
	        $this->session->set_flashdata('msg_to_show', $msg_to_show);
	        redirect('personnel/department', 'refresh');
		}
	}

	protected function check_valid_dep($where){
		$sql = "select count(deptid) as count_row from departments where ".$where;
		$view_user = $this->db->query($sql);
		$table_data=$view_user->result();
		if($table_data){
			foreach ($table_data as $row){
				$tot_row=$row->count_row;
				if($tot_row>0){
					return true; //- user already exist
				}
				else{
					return false; //- user not exist
				}
			}
		}
		else{
			return false; //- user not exist
		}
	}

	protected function check_valid_id($personal_id){
		$sql = "select count(userid) as count_row from userinfo where badgenumber='$personal_id'";
		$view_user = $this->db->query($sql);
		$table_data=$view_user->result();
		if($table_data){
			foreach ($table_data as $row){
				$tot_row=$row->count_row;
				if($tot_row>0){
					return true; //- user already exist
				}
				else{
					return false; //- user not exist
				}
			}
		}
		else{
			return false; //- user not exist
		}
	}

	public function import_department(){
		//==== INSERT TO ACTION LOG ===========================
		$this->util_function_model->insert_to_action_log($admin_id,'PERSONNEL-DEP-IMPORT','proc_new');
		//=====================================================

		$upload_file=$this->config->item('upload_file');
		$value='DEPARTMENTS.xls';
		$pathToFile=$upload_file."/".$value;

		//$params = array('file' => $pathToFile, 'store_extended_info' => true,'outputEncoding' => '');
		$this->load->library('Spreadsheet_Excel_Reader');
		//$data = new CI_Spreadsheet_Excel_Reader($pathToFile,false);


		$datestring = "%Y-%m-%d %H:%i:%s";
		$time = time();
		$curr_date= mdate($datestring, $time);

		// ExcelFile($filename, $encoding);
		$data = new CI_Spreadsheet_Excel_Reader($pathToFile);

		// Set output Encoding.
		$data->setOutputEncoding('CP1251');

		// Create an instance, passing the filename to create
		//$xls =& new Spreadsheet_Excel_Writer($file_name);

		$data->read($pathToFile);
		$num_of_sheet=sizeof($data->sheets);
		$data_to_insert=array();

		$tot_add_new=0;
		$tot_update=0;
		$admin_id=$this->session->userdata('admin_id');

		$datestring = "%Y-%m-%d %H:%i:%s";
		$time = time();
		$create_time= mdate($datestring, $time);

		$num_of_sheet=1;//- set $num_of_sheet=1 karena hanya 1 sheet yg akan diproses
		//-looping berdasarkan jumlah sheet
		for($curr_sheet=0;$curr_sheet<$num_of_sheet;$curr_sheet++){
			// Add a worksheet to the file, returning an object to add data to
			//$sheet =& $xls->addWorksheet($xls->sheets);

			//read data di masing2 sheet

			//- baca datanya
			//echo $data->sheets[$curr_sheet]['numRows']." *** ".$data->sheets[$curr_sheet]['numCols'];
			//die("");

			for ($i = 2; $i <= $data->sheets[$curr_sheet]['numRows']; $i++) { //- looping berdasarkan jumlah row
				for ($j = 2; $j <= $data->sheets[$curr_sheet]['numCols']; $j++) { //- looping berdasarkan jumlah column

					isset($data->sheets[$curr_sheet]['cells'][$i][$j]) ? $val_data=$data->sheets[$curr_sheet]['cells'][$i][$j] : $val_data='';
					//-DEPTID	DEPTNAME	SUPDEPTID	code

					if($j==2){
						$deptid=trim($val_data);
						//echo $deptid."<br>";
					}

					if($j==3){
						$deptname=trim($val_data);
						//echo $deptname."<br>";
					}

					if($j==4){
						$supdeptid=trim($val_data);
						//echo $supdeptid."<br>";
					}

					if($j==5){
						$code=trim($val_data);
						//echo $code."<br>";
					}
				}

				$tot_data=0;
				//$card_no
				$this->db->where('deptname',$deptname);
				$this->db->from('departments');
				$tot_data=$this->db->count_all_results();

				if($tot_data==0){
					$data_arr = array(
		               	'deptid' => $deptid,
		               	'deptname' => $deptname,
						'supdeptid' => $supdeptid,
						'code' => $code,
		               	'create_by' => $admin_id,
						'create_time' => $create_time,
						'update_by' => $admin_id,
						'update_time' => $create_time
		            );
					$this->db->insert('departments', $data_arr);
					$tot_add_new++;
				}
				else{
					$tot_update++;
				}
			}
		}

		$status_msg="
		<div class=\"alert alert-block alert-success\">
			<h4>Import Success</h4><br/>
			<p><strong>$tot_update</strong> row updated.<br/>
			<strong>$tot_add_new</strong> row imported.</p>
		</div>
		";

		$msg_to_show=$status_msg;
        $this->session->set_flashdata('msg_to_show', $msg_to_show);
        redirect('personnel/department', 'refresh');
		die("");
	}

	public function import_personnel(){
		//==== INSERT TO ACTION LOG ===========================
		$this->util_function_model->insert_to_action_log($admin_id,'PERSONNEL-IMPORT','proc_new');
		//=====================================================

		$upload_file=$this->config->item('upload_file');
		$value='PERSONNEL.xls';
		$pathToFile=$upload_file."/".$value;

		//$params = array('file' => $pathToFile, 'store_extended_info' => true,'outputEncoding' => '');
		$this->load->library('Spreadsheet_Excel_Reader');
		//$data = new CI_Spreadsheet_Excel_Reader($pathToFile,false);


		$datestring = "%Y-%m-%d %H:%i:%s";
		$time = time();
		$curr_date= mdate($datestring, $time);

		// ExcelFile($filename, $encoding);
		$data = new CI_Spreadsheet_Excel_Reader($pathToFile);

		// Set output Encoding.
		$data->setOutputEncoding('CP1251');

		// Create an instance, passing the filename to create
		//$xls =& new Spreadsheet_Excel_Writer($file_name);

		$data->read($pathToFile);
		$num_of_sheet=sizeof($data->sheets);
		$data_to_insert=array();

		$tot_update=0;
		$tot_add_new=0;
		$admin_id=$this->session->userdata('admin_id');

		$datestring = "%Y-%m-%d %H:%i:%s";
		$time = time();
		$create_time= mdate($datestring, $time);

		$num_of_sheet=1;//- set $num_of_sheet=1 karena hanya 1 sheet yg akan diproses
		//-looping berdasarkan jumlah sheet
		for($curr_sheet=0;$curr_sheet<$num_of_sheet;$curr_sheet++){
			// Add a worksheet to the file, returning an object to add data to
			//$sheet =& $xls->addWorksheet($xls->sheets);

			//read data di masing2 sheet

			//- baca datanya
			//echo $data->sheets[$curr_sheet]['numRows']." *** ".$data->sheets[$curr_sheet]['numCols'];
			//die("");
			$gender=1;

			for ($i = 2; $i <= $data->sheets[$curr_sheet]['numRows']; $i++) { //- looping berdasarkan jumlah row
				for ($j = 2; $j <= $data->sheets[$curr_sheet]['numCols']; $j++) { //- looping berdasarkan jumlah column

					isset($data->sheets[$curr_sheet]['cells'][$i][$j]) ? $val_data=$data->sheets[$curr_sheet]['cells'][$i][$j] : $val_data='';
					//-userid	badgenumber	name	plate_number	gender	defaultdeptid	cardno	street	create_by	create_time	update_by	update_time

					if($j==2){
						$userid=trim($val_data);
						//echo $userid."<br>";
					}

					if($j==3){
						$badgenumber=trim($val_data);
						//echo badgenumber."<br>";
					}

					if($j==4){
						$name=trim($val_data);
						//echo $name."<br>";
					}

					if($j==5){
						$defaultdeptid=trim($val_data);
						//echo $defaultdeptid."<br>";
					}

					if($j==6){
						$cardno=trim($val_data);
						//echo cardno."<br>";
					}

					if($j==7){
						$plate_number=trim($val_data);
						//echo $plate_number."<br>";
					}

					if($j==8){
						$street=trim($val_data);
						//echo street."<br>";
					}
				}

				$tot_data=0;
				$this->db->where('badgenumber',$badgenumber);
				$this->db->from('userinfo');
				$tot_data=$this->db->count_all_results();

				if($tot_data==0){
					//-adjusting card no
					$cardno=$this->util_function_model->adjust_card_no($cardno);

					//-check valid card
					$criteria='and reserved_status=0';
					$valid_card_no=$this->util_function_model->check_valid_card_no($cardno,$criteria);
					if($valid_card_no==''){
						$msg="Card No. $cardno is not authorized. Please choose another.";
						echo $msg;
			            die("");
					}
					else{
						if(!password_verify($cardno,$valid_card_no)){
							$msg="Card No. $cardno is not authorized. Please choose another.";
							echo $msg;
			            	die("");
						}
					}
					//======================================

					$name_split=explode("-", $name);
					if(count($name_split)==3){
						$name=$name_split[0];
						$plate_number=$name_split[1];
						$street=$name_split[2];
					}

					$data_arr = array(
		               	'userid' => $userid,
		               	'badgenumber' => $badgenumber,
						'name' => $name,
						'gender' => $gender,
						'defaultdeptid' => $defaultdeptid,
						'cardno' => $cardno,
						'street' => $street,
						'plate_number' => $plate_number,
		               	'create_by' => $admin_id,
						'create_time' => $create_time,
						'update_by' => $admin_id,
						'update_time' => $create_time
		            );
					$this->db->insert('userinfo', $data_arr);
					//=========================================

					$data_arr = array(
		               	'userid_id' => $userid,
		               	'cardno' => $cardno,
		               	'create_by' => $admin_id,
						'create_time' => $create_time,
						'update_by' => $admin_id,
						'update_time' => $create_time
		            );
					$this->db->insert('personnel_issuecard', $data_arr);
					//=========================================

					//-update reserved card table
					$ssql="
						update reserved_card set reserved_status=$userid, update_by=$admin_id,update_time='$create_time' where card_no_ori='$cardno'
					";
					$update_data = $this->db->query($ssql);
					//=========================================

					$tot_add_new++;
				}
				else{
					$tot_update++;
				}
			}
		}

		$status_msg="
		<div class=\"alert alert-block alert-success\">
			<h4>Import Success</h4><br/>
			<p><strong>$tot_update</strong> row updated.<br/>
			<strong>$tot_add_new</strong> row imported.</p>
		</div>
		";

		$msg_to_show=$status_msg;
        $this->session->set_flashdata('msg_to_show', $msg_to_show);
        redirect('personnel', 'refresh');
		die("");
	}

	public function import_access_level_emp(){
		//==== INSERT TO ACTION LOG ===========================
		$this->util_function_model->insert_to_action_log($admin_id,'DEVICE-ACCESS-LEVEL-IMPORT','proc_new');
		//=====================================================

		$upload_file=$this->config->item('upload_file');
		$value='acc_levelset_emp.xls';
		$pathToFile=$upload_file."/".$value;

		//$params = array('file' => $pathToFile, 'store_extended_info' => true,'outputEncoding' => '');
		$this->load->library('Spreadsheet_Excel_Reader');
		//$data = new CI_Spreadsheet_Excel_Reader($pathToFile,false);


		$datestring = "%Y-%m-%d %H:%i:%s";
		$time = time();
		$curr_date= mdate($datestring, $time);

		// ExcelFile($filename, $encoding);
		$data = new CI_Spreadsheet_Excel_Reader($pathToFile);

		// Set output Encoding.
		$data->setOutputEncoding('CP1251');

		// Create an instance, passing the filename to create
		//$xls =& new Spreadsheet_Excel_Writer($file_name);

		$data->read($pathToFile);
		$num_of_sheet=sizeof($data->sheets);
		$data_to_insert=array();

		$tot_add_new=0;
		$tot_update=0;
		$admin_id=$this->session->userdata('admin_id');

		$datestring = "%Y-%m-%d %H:%i:%s";
		$time = time();
		$create_time= mdate($datestring, $time);

		$num_of_sheet=1;//- set $num_of_sheet=1 karena hanya 1 sheet yg akan diproses
		//-looping berdasarkan jumlah sheet
		for($curr_sheet=0;$curr_sheet<$num_of_sheet;$curr_sheet++){
			// Add a worksheet to the file, returning an object to add data to
			//$sheet =& $xls->addWorksheet($xls->sheets);

			//read data di masing2 sheet

			//- baca datanya
			//echo $data->sheets[$curr_sheet]['numRows']." *** ".$data->sheets[$curr_sheet]['numCols'];
			//die("");

			for ($i = 2; $i <= $data->sheets[$curr_sheet]['numRows']; $i++) { //- looping berdasarkan jumlah row
				for ($j = 2; $j <= $data->sheets[$curr_sheet]['numCols']; $j++) { //- looping berdasarkan jumlah column

					isset($data->sheets[$curr_sheet]['cells'][$i][$j]) ? $val_data=$data->sheets[$curr_sheet]['cells'][$i][$j] : $val_data='';
					//-acclevelset_id	employee_id

					if($j==2){
						$acclevelset_id=trim($val_data);
						//echo $acclevelset_id."<br>";
					}

					if($j==3){
						$employee_id=trim($val_data);
						//echo $employee_id."<br>";
					}
				}

				$tot_data=0;
				//$card_no
				$this->db->where('acclevelset_id',$acclevelset_id);
				$this->db->where('employee_id',$employee_id);
				$this->db->from('acc_levelset_emp');
				$tot_data=$this->db->count_all_results();

				if($tot_data==0){
					$data_arr = array(
		               	'acclevelset_id' => $acclevelset_id,
		               	'employee_id' => $employee_id
		            );
					$this->db->insert('acc_levelset_emp', $data_arr);
					$tot_add_new++;
				}
				else{
					$tot_update++;
				}
			}
		}

		$status_msg="
		<div class=\"alert alert-block alert-success\">
			<h4>Import Success</h4><br/>
			<p><strong>$tot_update</strong> row updated.<br/>
			<strong>$tot_add_new</strong> row imported.</p>
		</div>
		";

		$msg_to_show=$status_msg;
        $this->session->set_flashdata('msg_to_show', $msg_to_show);
        redirect('personnel/department', 'refresh');
		die("");
	}

	public function nextdate() {
		echo date('Y-m-d', strtotime( date('Y-m-d').' +1 day') );
	}

	public function calcpin() {
		$lastpin = $this->db->select("max(badgenumber) + 1 AS pin_max")
		->from("userinfo")->get()->row();

		$newlastpin = $lastpin->pin_max;

		echo $newlastpin;
	}

	public function visitor() {
		$data['base']			= $this->config->item('base_url');
		$data['images_folder']	= $this->config->item('images');
		$data['images_icon']	= $this->config->item('images_icon');

		$data['js']				= $this->config->item('js');
		$data['css']			= $this->config->item('css');
		$data['author']			= $this->config->item('author');

		$data['site_title']		= $this->config->item('site_title');
		$data['page_heading']	= "Visitor Page";
		$data['display_info']=$this->session->flashdata('msg_to_show');

		$admin_id=$this->session->userdata('admin_id');
		$admin_name=$this->session->userdata('admin_name');
		$contact_level=$this->session->userdata('admin_level');

		$data['admin_name']=$admin_name;

		//============ CHECK PRIV FOR SECURITY ========================
		if($contact_level==1){
			$val_write=TRUE;
		}

		$data['val_write']=$val_write;
		//========================================================

		/*generate tables*/
		$this->load->model('table_model'); //- load table model

		/**** styling the table ****/
		//- you can describe your own template here by use the variable of $tmpl_table
		$tmpl_table='';//- used default style
		$this->table_model->set_template($tmpl_table); //- set template
		$this->table->set_heading( 'No.','Person Name', 'Person Photo', 'Visitor Pin',
		'KTP Number','Gender','Visit Validity','Action'); //- create the header of table

		$row_no=1;

		$data['cmb_access_level']="<select id=\"access_level[]\" name=\"access_level[]\" data-placeholder=\"Select Access Level...\" class=\"chosen-access\" multiple>";
		$ssql="
				select id,level_name from acc_levelset
				";
		$view_dep = $this->db->query($ssql);
		$table_data=$view_dep->result();
		if($table_data){
			foreach ($table_data as $row)
			{
				$id=$row->id;
				$level_name=$row->level_name;
				$data['cmb_access_level'].="<option value=\"$id\">$level_name</option>";
			}
		}
		$data['cmb_access_level'].="</select>";

		$view_data = $this->db->select("userid, badgenumber, name, ktp_number, gender, person_photo, valid_date")
		->from("userinfo")->where("person_status", "vst")->order_by("valid_date", "asc")->get();
		$table_data=$view_data->result();

		if($table_data){

			foreach ($table_data as $row) {
				$visitor_pin = $row->badgenumber;
				$gender = $row->gender;
				$gender_name = ($gender == 'w') ? 'Female' : 'Male';

				if($val_write){
					$icon_detail="<img src=\"".$data['images_icon']."/icons/edit.png\" title=\"Edit\" class=\"edit_row\" data-target=\"#form\" data-toggle=\"modal\">&nbsp;&nbsp;";
				}

				$rownoid = [
					'data' => $row_no++.".",
					'data-userid' => $row->userid
				];
				$action_button="<div align=\"left\">$icon_detail</div>";

				$person_name = array('data' => $row->name);

				$this->table->add_row(
					$rownoid, $person_name,
					"<figure><a href=\"" ."." .$row->person_photo ."\" data-fancybox=\"person\">
					<img src=\"" ."." .$row->person_photo ."\" width=\"63\" class=\"img-thumbnail\">
					</a><figcaption><p>Name: $row->name</p><p>Gender: $gender_name</p></figcaption>
					</figure>",
					$visitor_pin, $row->ktp_number, $gender_name, $row->valid_date, $action_button
				);

			}

			$data['display_table']=$this->table->generate();
		}
		else
		{
			$data['display_table']=$this->util_function_model->info_msg("You don't have any data to show.");
		}

		$this->table->clear();
		/*end tables*/

		$data['dyn_menu']=$this->load->view('templates/dyn_menu',$data,true);
		$this->load->view('templates/header',$data);
		$this->load->view('personnel/visitor',$data);
		$this->load->view('templates/footer');
	}

	public function checkPIN() {

		if( $this->check_valid_id( $this->input->post('chPIN') ) ) {
			$status_msg = "*PIN already used by another personnel.";
		} else {
			$status_msg = "PIN is available.";
		}

		echo $status_msg;
	}

	public function visitor_act() {
		$folder_lct = $this->config->item('upload_file')."/visitor/";
		$proc_mode = $this->input->post('act_mode');

	if ($proc_mode == 'add_mode' || $proc_mode == 'edit_mode') {
		$vstpin = $this->input->post('txt_vst_pin');
/*
		-check valid personnel id if( $this->check_valid_id($vstpin) ){*/

		$person_name = $this->input->post('txt_person');
		$ktp_number = $this->input->post('txt_ktp');
		$gender = $this->input->post('gender_group');

		$img = $this->input->post('img_c');

		if( $img ) {
			$image_parts = explode(";base64,", $img);
    	$image_base64 = base64_decode( $image_parts[1] );
			$file_name = $vstpin.'.jpg';

			$img_target = $folder_lct.$file_name;
  		file_put_contents($img_target, $image_base64);

			$encodeimg = file_get_contents($img_target);
			$base64img = base64_encode($encodeimg);
			$img_url = str_replace( './', 'viovera.access.control/', $img_target );
		} else {

			$oldphoto = $this->db->select("person_photo")
			->from("userinfo")
			->where("userid", $this->input->post('kd_pin') )
			->get()
			->row();

			$prsoldphoto = $oldphoto->person_photo;

			$encodeimg = file_get_contents($prsoldphoto);
			$base64img = base64_encode($encodeimg);
			$img_url = str_replace( './', 'viovera.access.control/', $prsoldphoto);
		}

		if ( $proc_mode == 'add_mode' ) {
			$insertuserinfo = array(
				'badgenumber' => $vstpin,
				'person_status' => 'vst',

				'photo_base64' => $image_parts[ 1 ],
				'person_photo' => $img_target,
				'name' => $person_name,

				'ktp_number' => $ktp_number,
				'gender' => $gender,
				'valid_date' => $this->input->post('txt_vld_date')
			);
			$this->db->insert('userinfo', $insertuserinfo);

			$insertvisitor = [
				'photo' => $img_target,
				'photo_base64' => $image_parts[ 1 ],

				'visitor_pin' => $vstpin,
				'person_name' => $person_name,
				'ktp_number' => $ktp_number,

				'gender' => $gender,
				'valid_date' => $this->input->post('txt_vld_date')
			];
			$this->db->insert('visitor', $insertvisitor);

			$status_msg="Success! New visitor has been added.";

			} else if ($proc_mode == 'edit_mode') {
				$kdpin = $this->input->post('kd_pin');

				if ($img) {
					$data_arr = [
						'badgenumber' => $vstpin,
						'photo_base64' => $image_parts[ 1 ],
						'person_photo' => $img_target,

						'name' => $person_name,
						'ktp_number' => $ktp_number,
						'gender' => $gender
					];

				} else {

					$data_arr = [
						'badgenumber' => $vstpin,
						'name' => $person_name,
						'ktp_number' => $ktp_number,

						'gender' => $gender
					];

				}
				$this->db->where('userid', $kdpin );
				$this->db->update('userinfo', $data_arr);

				$status_msg = "Ok! Visitor is updated.";
			}

			$count_status = $this->db->select('count(push_id) + 1 AS zero_status')
			->from('device_push_command')
			->where('push_status', 0)
			->get()->row();

			$zero_sts = $count_status->zero_status;

			$insertdevicepush = [
				'device_id' => 2,
				'push_cmd' => 'C:'.$zero_sts++.':DATA UPDATE user CardNo=\tPin='.$vstpin.'\tPassword=\tGroup=0\tStartTime=0\tEndTime=0\tName='.$person_name.'\tPrivilege=0\t\n'
			];
			$this->db->insert('device_push_command', $insertdevicepush);

			$insertdevicepush = [
				'device_id' => 2,
				'push_cmd' => 'C:'.$zero_sts++.':DATA UPDATE extuser Pin='.$vstpin.'\tFunSwitch=0\tFirstName='.$person_name.'\tLastName=\t\n'
			];
			$this->db->insert('device_push_command', $insertdevicepush);

			$insertdevicepush = [
				'device_id' => 2,
				'push_cmd' => 'C:'.$zero_sts++.':DATA UPDATE userpic pin='.$vstpin.'\tsize='.strlen($base64img).'\tcontent='.$base64img.'\t\n'
			];
			$this->db->insert('device_push_command', $insertdevicepush);

			$insertdevicepush = [
				'device_id' => 2,
				'push_cmd' => 'C:'.$zero_sts++.':DATA UPDATE biophoto PIN='.$vstpin.'\tType=9\tSize=0\tContent=\tFormat=1\tUrl='.$img_url.'\t\n'
			];
			$this->db->insert('device_push_command', $insertdevicepush);

			$insertdevicepush = [
				'device_id' => 2,
				'push_cmd' => 'C:'.$zero_sts++.':DATA UPDATE userauthorize Pin='.$vstpin.'\tAuthorizeTimezoneId=1\tAuthorizeDoorId=1\t\n'
			];
			$this->db->insert('device_push_command', $insertdevicepush);

	} else if ($proc_mode == 'del_mode') {

		$pin = $this->input->post('kd_pin');
		$this->db->where('', $pin);
		$this->db->delete('');

		$status_msg="Visitor is deleted.";
	}

		$msg_to_show=$this->util_function_model->info_msg($status_msg);
		$this->session->set_flashdata('msg_to_show', $msg_to_show);

		redirect('personnel/visitor', 'refresh');
		die("");
	}

	public function personnel_access(){
		$data['base']			= $this->config->item('base_url');
		$data['images_folder']	= $this->config->item('images');
		$data['images_icon']	= $this->config->item('images_icon');
		$data['js']				= $this->config->item('js');
		$data['css']			= $this->config->item('css');
		$data['author']			= $this->config->item('author');
		$data['site_title']		= $this->config->item('site_title');

		$data['page_heading']	= "Personnel Access";

		$data['display_info']=$this->session->flashdata('msg_to_show');
		$admin_id=$this->session->userdata('admin_id');
		$admin_name=$this->session->userdata('admin_name');
		$contact_level=$this->session->userdata('admin_level');
		//========================================================

		$data['admin_name']=$admin_name;

		//============ CHECK PRIV FOR SECURITY ========================
		if($contact_level==1){
			$val_write=TRUE;
		}

		$data['val_write']=$val_write;
		//========================================================

		$data['cmb_access_level']="<select name=\"access_level[]\" data-placeholder=\"Select Access Level...\" class=\"chosen-select\" multiple>";
		$ssql="
				select id,level_name from acc_levelset
				";
		$view_dep = $this->db->query($ssql);
		$table_data=$view_dep->result();
		if($table_data){
			foreach ($table_data as $row)
			{
				$id=$row->id;
				$level_name=$row->level_name;
				$data['cmb_access_level'].="<option value=\"$id\">$level_name</option>";
			}
		}
		$data['cmb_access_level'].="</select>";

		/*generate tables*/
		$this->load->model('table_model'); //- load table model

		/**** styling the table ****/
		//- you can describe your own template here by use the variable of $tmpl_table
		$tmpl_table='';//- used default style
		$this->table_model->set_template($tmpl_table); //- set template
		$this->table->set_heading( 'No.','Person Name','Photo','Pin ID','KTP Number','Gender','Action'); //- create the header of table

		$row_no=1;

		$view_data = $this->db->select("badgenumber, name, ktp_number, gender, person_photo")
		->from("userinfo")->where('person_status', 'pac')->get();
		$table_data=$view_data->result();
		if($table_data){
			foreach ($table_data as $row) {
				$access_pin = $row->badgenumber;
				$gender = $row->gender;
				$gender_name = ($gender == 'w') ? $gender_name = 'Female' : $gender_name = 'Male';
				if($val_write){
					$icon_delete="<img src=\"".$data['images_icon']."/icons/delete.png\" title=\"Delete\" onclick=\"confirm_delete($access_pin)\">&nbsp;&nbsp;";
					$icon_detail="<img src=\"".$data['images_icon']."/icons/edit.png\" title=\"Edit\" class=\"edit_row\" data-target=\"#form\" data-toggle=\"modal\">&nbsp;&nbsp;";
				}
				$action_button="<div align=\"left\">$icon_detail$icon_delete</div>";

				    $data_arr = array(
				   	$row_no++.".",
						$row->name,
						"<img width=\"39\" src=\"" ."." .$row->person_photo ."\" class=\"img-thumbnail\">",
						$access_pin,
						$row->ktp_number,
						$gender_name,
						$action_button
		        );

				$this->table->add_row($data_arr);
			}

			$data['display_table']=$this->table->generate();
		}
		else{
			$data['display_table']=$this->util_function_model->info_msg("You don't have any data to show.");
		}

		$this->table->clear();
		/*end tables*/

		$data['dyn_menu']=$this->load->view('templates/dyn_menu',$data,true);
		$this->load->view('templates/header',$data);
		$this->load->view('personnel/personnel_access',$data);
		$this->load->view('templates/footer');
	}

	public function personnel_access_act(){
		$proc_mode = $this->input->post('act_mode');

		$folder_lct = $this->config->item('upload_file')."/personnel/";

	if ($proc_mode == 'add_mode') {

		$person_name = $this->input->post('txt_person');
		$ktp_number = $this->input->post('txt_ktp');
		$gender = $this->input->post('gender_group');

		$file_name = $ktp_number.$person_name.time().'.jpg';
		$img_target = $folder_lct.$file_name;

		$toinsert = array(
			'person_photo' => $img_target,
			'person_name' => $person_name,
			'ktp_number' => $ktp_number,
			'gender' => $gender
		);
		$this->db->insert('userinfo', $toinsert);

		$status_msg="Successful! New Personnel Access has been added.";

	} else if ($proc_mode == 'edit_mode'){

		$person_name = $this->input->post('txt_person');
		$ktp_number = $this->input->post('txt_ktp');
		$gender = $this->input->post('gender_group');

		$pin = $this->input->post('kd_pin');

		$data_arr = [
			'photo_base64' => '',
			'person_photo' => '',
			'badgenumber' => '',

			'name' => $person_name,
			'ktp_number' => $ktp_number,
			'gender' => $gender

		];
		$this->db->where('userid', $pin);
		$this->db->update('userinfo', $data_arr);

		$status_msg="Ok! Personnel Access is updated.";

	} else if ($proc_mode == 'del_mode') {

		$pin = $this->input->post('kd_pin');
		$this->db->where('access_pin', $pin);
		$this->db->delete('personnel_access');

		$status_msg="Personnel Access has been deleted.";
	}
		$msg_to_show=$this->util_function_model->info_msg($status_msg);
		$this->session->set_flashdata('msg_to_show', $msg_to_show);

		redirect('personnel/personnel_access', 'refresh');
		die("");
	}

}

/* End of file act_code.php */
/* Location: ./application/controllers/act_code.php */
