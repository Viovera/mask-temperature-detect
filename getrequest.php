<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class getrequest extends CI_Controller {

	public function getrequest()
	{
		parent::__construct();
		$this->load->model('db_functions_model'); //- load database model
		$this->load->model('employee_model');
		$this->load->model('util_function_model');
		$this->load->helper('date');
		$this->load->helper('url');
	}

	public function index($offset=0)
	{
		$vioveradb = $this->load->database('vioveradb', TRUE); // the TRUE paramater tells CI that you'd like to return the database object.
		$curr_db=$vioveradb;
		$vioveradb->reconnect();

		//============ DECRYPTION =============================
		$encrKey="87345907593824534534952345";
		//=====================================================

		//$sn = $_REQUEST['SN'];//@file_get_contents('php://input');
		isset($_REQUEST["SN"]) ? $sn=$_REQUEST["SN"] : $sn="";
		isset($_REQUEST["INFO"]) ? $INFO=$_REQUEST["INFO"] : $INFO="";
		$httprequest=$_SERVER['QUERY_STRING'];

		$datestring = "%Y-%m-%d %H:%i:%s";
		$time = time();
		$curr_date= mdate($datestring, $time);

		$admin_id=1;
		$device_id=2;/*
		if($sn!=''){
			$activation_key=$this->employee_model->EnCryptXOR($sn,$encrKey);
			$device_id=0;
			$device_info=$this->employee_model->get_device_info_via($activation_key,$sn,$curr_db);
			$device_info=explode("*", $device_info);
			$device_id=$device_info[0];
		}*/

		if($device_id>0){
			header('Content-Type: text/plain');

			if($INFO!=""){
				$INFO_split=explode(",", $INFO);
			}
			else{

				$path = 'http://localhost:8090/viovera.access.control/assets/upload_file/visitor/876.jpg';
				$type = pathinfo($path, PATHINFO_EXTENSION);
				$data = file_get_contents($path);
				$base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

				//echo base64_encode($data);
				//die("");

				//echo "C:122:CHECK\n";
				//die("");
				//$command_parameter='OK';
			//echo "C:1:DATA DELETE userauthorize\tPin=1\tPin=2";
			/*
				echo "C:1:DATA DELETE userauthorize\tPin=1\t\n";
				echo "C:2:DATA DELETE biodata Pin=1\tType=1\t\n";
				echo "C:3:DATA DELETE biodata Pin=1\tType=8\t\n";
				echo "C:4:DATA DELETE biodata Pin=1\tType=9\t\n";
				echo "C:5:DATA DELETE user Pin=1\t\n";
				echo "C:6:DATA DELETE extuser Pin=1\t\n";
			*/

//echo "C:1:DATA UPDATE user CardNo=\tPin=874\tPassword=\tGroup=0\tStartTime=0\tEndTime=0\tName=Samar\tPrivilege=0\t\nC:2:DATA UPDATE extuser Pin=874\tFunSwitch=0\tFirstName=Samar\tLastName=Sekali\t\nC:5:DATA UPDATE userauthorize Pin=874\tAuthorizeTimezoneId=1\tAuthorizeDoorId=1\t\n";
//echo "C:1:DATA UPDATE user CardNo=\tPin=874\tPassword=\tGroup=0\tStartTime=0\tEndTime=0\tName=Samar\tPrivilege=0\t\nC:2:DATA UPDATE extuser Pin=874\tFunSwitch=0\tFirstName=Samar\tLastName=Sekali\t\nC:5:DATA UPDATE userauthorize Pin=874\tAuthorizeTimezoneId=1\tAuthorizeDoorId=1\t\n";
				//echo "C:1:DATA UPDATE user CardNo=\tPin=874\tPassword=\tGroup=0\tStartTime=0\tEndTime=0\tName=Bolek\tPrivilege=0\t\n";
				//echo "C:2:DATA UPDATE extuser Pin=874\tFunSwitch=0\tFirstName=Bolek\tLastName=Sitompul\t\n";
				//echo "C:3:DATA UPDATE userpic pin=874\tsize=".strlen(base64_encode($data))."\tcontent=".base64_encode($data)."\t\n";
				//echo "C:4:DATA UPDATE biophoto PIN=874\tType=9\tSize=0\tContent=\tFormat=1\tUrl=viovera.access.control/assets/upload_file/visitor/883.jpg\t\n";
				//echo "C:5:DATA UPDATE userauthorize Pin=874\tAuthorizeTimezoneId=1\tAuthorizeDoorId=1\t\n";
				//echo "C:6:DATA UPDATE extuser\t\Pin=874\t\FunSwitch=0\t\FirstName=Bolek\t\LastName=Sitompul\t\n";
/*
				echo "C:1:DATA UPDATE user CardNo=\tPin=874\tPassword=\tGroup=0\tStartTime=0\tEndTime=0\tName=Samar\tPrivilege=0\t\n";
				echo "C:2:DATA UPDATE extuser Pin=874\tFunSwitch=0\tFirstName=Samar\tLastName=Sekali\t\n";
				echo "C:5:DATA UPDATE userauthorize Pin=874\tAuthorizeTimezoneId=1\tAuthorizeDoorId=1\t\n";
*/
header('Content-Type: text/plain');
				$push_cmd='';
				$command_parameter='';
				$query="
						push_cmd
					";

				$table_name="device_push_command t1";
				$curr_db->where('device_id',$device_id);
				$curr_db->where('push_status',0);
				$curr_db->where('push_date <=', $curr_date);
				$curr_db->order_by('push_id');
				//$curr_db->limit(1,0);
				$curr_db->select($query);
				$curr_db->from($table_name);

				//==============================================
				$table_data=$this->db_functions_model->get_data_query($query,$curr_db); //- parsing query
				if($table_data){
					foreach ($table_data as $row)
					{
						$command_parameter=$row->push_cmd;

							$push_cmd.=$command_parameter;
							//print $push_cmd;
							//die("");
					}
					echo $push_cmd;
				}
				else{
					echo "OK";
				}
				die("");


				//echo $command_parameter;
				//echo "OK";
				die("");
			}

			/*
			$exe_param="";
			$query="
					(select top 1 activation_key from tbl_devices t2 where t1.device_id=t2.device_id) sn_no,
					command_parameter
				";

			$table_name="device_command_push t1";
			$curr_db->where('comp_id',$comp_id);
			$curr_db->where('command_status',0);
			$curr_db->where('command_action','getrequest');
			$curr_db->order_by('ref_id','ASC');
			$curr_db->select($query);
			$curr_db->from($table_name);
			//==============================================
			$table_data=$this->db_functions_model->get_data_query($query,$curr_db); //- parsing query
			if($table_data){
				foreach ($table_data as $row)
				{
					$sn_no=$row->sn_no;

					$command_parameter=$row->command_parameter;
					if($sn_no==$sn){
						$exe_param.=$command_parameter;
						print $exe_param;
						die("");
					}
				}
			}
			*/
		}

			//if($sn=='5305392360002'){
				//echo "C:123:SetTZInfo TZIndex=1\tTZ=00:00-00:00;00:00-00:00;00:00-00:00;00:00-00:00;20:00-21:00;00:00-00:00;00:00-00:00\tTZMode=1\n";
				//echo "C:124:SetTZInfo TZIndex=2\tTZ=00:00-00:00;00:00-00:00;00:00-00:00;00:00-00:00;15:00-18:00;00:00-00:00;00:00-00:00\tTZMode=1\n";
				//print "C:1:DATA QUERY USERINFO=600192\n";
			//}
			//echo "C:123:DATA QUERY AttLog\n";
			//echo "C:1:DATA QUERY ATTLOG StartTime=2017-09-15 00:00:00\tEndTime=2017-09-17 00:00:00\n";


			//print "C:1:SET OPTION WebServerIP=192.168.1.104\n";
			//print "C:1:DATA DELETE USERINFO PIN=123\t\n";
			//print "C:122:INFO\n";
			//print "C:120:CHECK\n";
			//echo "C:2:DATA USER PIN=110795\t\n";
			//print "C:123:DATA USER PIN=8899\n";
			//echo "C:2:REBOOT\n";
			//echo "C:1:DATA UPDATE USERINFO PIN=123\tName=coco\t\n";
			//echo "C:1:DATA QUERY ATTLOG StartTime=2014-07-01 00:00:00\tEndTime=2015-07-10 00:00:00\n";
			//print "C:1:DATA QUERY USERINFO=1002\n";
			//echo "C:123:DATA QUERY USERINFO\n";
			//echo "C:2:DATA UPDATE USERINFO PIN=1002\tName=cisco\t\n";
			//echo "C:1:DATA UPDATE SMS MSG=hello world\tTAG=254\tUID=2\tMIN=10\tStartTime=2015-04-24 00:00:00\n";
			//echo "C:1:DATA UPDATE USER_SMS PIN=8899\tUID=2\n";
			//echo "C:1:DATA UPDATE WORKCODE Code=1\tName=test\n";
			//print "C:120:ENROLL_FP USERINFO PIN=8080\tFID=2\tRETRY=3\tOVERWRITE=1\n";
			//echo "OK\n";
			//die("");

			/*
			$server_ip   = '192.168.0.10';
			$server_port = 4374;
			$beat_period = 5;
			$message     = 'DATA';
			//print "Sending heartbeat to IP $server_ip, port $server_port\n";
			//print "press Ctrl-C to stop\n";
			if ($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) {
			  while (1) {
			    socket_sendto($socket, $message, strlen($message), 0, $server_ip, $server_port);
			    //print "Time: " . date("%r") . "\n";
			    sleep($beat_period);
			  }
			} else {
			  //print("can't create socket\n");
			}
			*/
		//}
	}
}

/* End of file cdata.php */
/* Location: ./application/controllers/cdata.php */
