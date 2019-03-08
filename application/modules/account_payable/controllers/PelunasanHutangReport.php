<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class PelunasanHutangReport extends MY_Controller {
	
	public $table;
		
	function __construct()
	{
		parent::__construct();
		$this->prefix_apps = config_item('db_prefix');
		$this->prefix = config_item('db_prefix2');
		$this->prefix_acc = config_item('db_prefix3');
		$this->load->model('model_pelunasanap', 'm');
	}
	
	public function print_pelunasanHutangReport(){
		
		$this->table = $this->prefix_acc.'pelunasan_ap';
		
		$session_user = $this->session->userdata('user_username');					
		$user_fullname = $this->session->userdata('user_fullname');					
		
		if(empty($session_user)){
			die('Sesi Login sudah habis, Silahkan Login ulang!');
		}
		
		extract($_GET);
		
		$data_post = array(
			'do'	=> '',
			'report_data'	=> array(),
			'report_place_default'	=> '',
			'report_name'	=> 'PELUNASAN HUTANG REPORT',
			'date_from'	=> $date_from,
			'date_till'	=> $date_till,
			'user_fullname'	=> $user_fullname
		);
		
		$get_opt = get_option_value(array('report_place_default'));
		if(!empty($get_opt['report_place_default'])){
			$data_post['report_place_default'] = $get_opt['report_place_default'];
		}
		
		if(empty($date_from) OR empty($date_till)){
			die('Data Not Found!');
		}else{
				
			if(empty($date_from)){ $date_from = date('Y-m-d'); }
			if(empty($date_till)){ $date_till = date('Y-m-d'); }
			
			$mktime_dari = strtotime($date_from);
			$mktime_sampai = strtotime($date_till);
						
			$qdate_from = date("Y-m-d",strtotime($date_from));
			$qdate_till = date("Y-m-d",strtotime($date_till));
			
			$add_where = "(a.pelunasan_date >= '".$qdate_from."' AND a.pelunasan_date <= '".$qdate_till."')";
			
			$this->db->select("a.*, b.kb_name");
			$this->db->from($this->table." as a");
			$this->db->join($this->prefix_acc.'kontrabon as b','b.id = a.kb_id','LEFT');
			
			if(!empty($status)){
				$this->db->where("a.pelunasan_status = '".$status."'");
				$data_post['pelunasan_status'] = $status;
			}
			
			$this->db->where("a.is_deleted", 0);
			$this->db->where($add_where);
			$this->db->order_by("pelunasan_date","ASC");
			$get_dt = $this->db->get();
			if($get_dt->num_rows() > 0){
				$data_post['report_data'] = $get_dt->result_array();				
			}
						
			$all_pelunasan_id = array();
			$newData = array();
			if(!empty($data_post['report_data'])){
				foreach ($data_post['report_data'] as $s){
					$s['created_date'] = date("d-m-Y H:i",strtotime($s['created']));					
					$s['pelunasan_date'] = date("d-m-Y",strtotime($s['pelunasan_date']));
					
					if(!in_array($s['id'], $all_pelunasan_id)){
						$all_pelunasan_id[] = $s['id'];
					}		
										
					$s['pelunasan_total_text'] = priceFormat($s['pelunasan_total']);
					
					$newData[$s['id']] = $s;
					//array_push($newData, $s);
					
				}
			}
			
			$data_post['report_data'] = $newData;
		}
		
		//DO-PRINT
		if(!empty($do)){
			$data_post['do'] = $do;
		}else{
			$do = '';
		}
		
		$useview = 'print_pelunasanHutangReport';
		if($do == 'excel'){
			$useview = 'excel_pelunasanHutangReport';
		}
				
		$this->load->view('../../account_payable/views/'.$useview, $data_post);	
	}
	

}