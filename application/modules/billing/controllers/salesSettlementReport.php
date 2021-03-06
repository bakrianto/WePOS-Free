<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class SalesSettlementReport extends MY_Controller {
	
	public $table;
		
	function __construct()
	{
		parent::__construct();
		$this->prefix_apps = config_item('db_prefix');
		$this->prefix = config_item('db_prefix2');
		$this->load->model('model_databilling', 'm');
		$this->load->model('model_billingdetail', 'm2');
	}	
	
	public function print_salesSettlementReport(){
		$this->table = $this->prefix.'billing';
		$this->table2 = $this->prefix.'billing_detail';		
		
		$session_user = $this->session->userdata('user_username');					
		$user_fullname = $this->session->userdata('user_fullname');					
		
		if(empty($session_user)){
			die('Sesi Login sudah habis, Silahkan Login ulang!');
		}
		
		extract($_GET);
		
		if(empty($date_from)){ $date_from = date("Y-m-d"); }
		if(empty($date_till)){ $date_till = date("Y-m-d"); }
		
		if(empty($sorting)){
			$sorting = 'payment_date';
		}
		
		$exp_date_from = explode("-",$date_from);
		$exp_date_till = explode("-",$date_till);
		$mk_date_from = strtotime($exp_date_from[2]."-".$exp_date_from[1]."-".$exp_date_from[0]." 00:00:01");
		$mk_date_till = strtotime($exp_date_till[2]."-".$exp_date_till[1]."-".$exp_date_till[0]." 23:59:59");
		
		$total_day = ceil(($mk_date_till - $mk_date_from) / ONE_DAY_UNIX);
		
		$data_post = array(
			'do'	=> '',
			'report_data'	=> array(),
			'report_place_default'	=> '',
			'report_name'	=> 'SALES SUMMARY REPORT',
			'date_from'	=> $date_from,
			'mk_date_from'	=> $mk_date_from,
			'date_till'	=> $date_till,
			'mk_date_till'	=> $mk_date_till,
			'total_day'	=> $total_day,
			'cashier_name'	=> '',
			'user_fullname'	=> $user_fullname,
			'diskon_sebelum_pajak_service'	=> 0,
			'display_discount_type'	=> array()
		);
		
		$display_discount_type = array();

		if(empty($groupCat)){
			$groupCat = 0;
		}
		
		$get_opt = get_option_value(array('report_place_default','diskon_sebelum_pajak_service','cashier_max_pembulatan',
		'cashier_pembulatan_keatas','pembulatan_dinamis','role_id_kasir','maxday_cashier_report',
		'jam_operasional_from','jam_operasional_to','jam_operasional_extra'));
		if(!empty($get_opt['report_place_default'])){
			$data_post['report_place_default'] = $get_opt['report_place_default'];
		}
		if(!empty($get_opt['diskon_sebelum_pajak_service'])){
			$data_post['diskon_sebelum_pajak_service'] = $get_opt['diskon_sebelum_pajak_service'];
		}
		if(empty($get_opt['cashier_max_pembulatan'])){
			$get_opt['cashier_max_pembulatan'] = 0;
		}
		if(empty($get_opt['cashier_pembulatan_keatas'])){
			$get_opt['cashier_pembulatan_keatas'] = 0;
		}
		if(empty($get_opt['pembulatan_dinamis'])){
			$get_opt['pembulatan_dinamis'] = 0;
		}
		
		if(empty($date_from) OR empty($date_till)){
			die('Billing Data Not Found!');
		}else{
				
			if(empty($date_from)){ $date_from = date('Y-m-d'); }
			if(empty($date_till)){ $date_till = date('Y-m-d'); }
			
			$mktime_dari = strtotime($date_from);
			$mktime_sampai = strtotime($date_till);
						
			$ret_dt = check_maxview_cashierReport($get_opt, $mktime_dari, $mktime_sampai);
			
			//$qdate_from = date("Y-m-d",strtotime($date_from));
			//$qdate_till = date("Y-m-d",strtotime($date_till));
			//$qdate_till_max = date("Y-m-d",strtotime($date_till)+ONE_DAY_UNIX);
			//$add_where = "(b.payment_date >= '".$qdate_from." 07:00:00' AND b.payment_date <= '".$qdate_till_max." 06:00:00')";
			
			//laporan = jam_operasional
			$qdate_from = $ret_dt['qdate_from'];
			$qdate_till = $ret_dt['qdate_till'];
			$qdate_till_max = $ret_dt['qdate_till_max'];
			$add_where = "(b.payment_date >= '".$qdate_from."' AND b.payment_date <= '".$qdate_till_max."')";
			
			//b.tax_total, b.service_total,
			//b.include_tax, b.tax_percentage, b.include_service, b.service_percentage, b.is_compliment,
			$this->db->select("a.*, b.billing_no, b.total_billing, b.grand_total, b.discount_perbilling, b.payment_id, b.bank_id,
								b.is_half_payment, b.total_cash, b.total_credit, b.total_dp, b.compliment_total,
								b.discount_percentage as billing_discount_percentage, b.discount_total as billing_discount_total,
								b.total_pembulatan as billing_total_pembulatan, b.created as billing_date, b.payment_date, b.diskon_sebelum_pajak_service,
								c.product_name, c.product_group, c.category_id, d.product_category_name as category_name, 
								g.nama_shift");
			$this->db->from($this->table2." as a");
			$this->db->join($this->prefix.'billing as b','b.id = a.billing_id','LEFT');
			$this->db->join($this->prefix.'product as c','c.id = a.product_id','LEFT');
			$this->db->join($this->prefix.'product_category as d','d.id = c.category_id','LEFT');
				$this->db->join($this->prefix.'shift as g','g.id = b.shift','LEFT');
			$this->db->where("a.is_deleted", 0);
			$this->db->where("b.is_deleted", 0);
			$this->db->where("b.billing_status", "paid");	
			$this->db->where($add_where);		
			$this->db->order_by("d.product_category_name", 'ASC');
			$this->db->order_by("c.product_name", 'ASC');
			
			if(empty($sorting)){
				$this->db->order_by("payment_date","ASC");
			}else{
				$this->db->order_by($sorting,"ASC");
			}
			
			$get_dt = $this->db->get();
			if($get_dt->num_rows() > 0){
				$data_post['report_data'] = $get_dt->result_array();
			}
			
			//PAYMENT DATA
			$dt_payment_name = array();
			$this->db->select('*');
			$this->db->from($this->prefix.'payment_type');
			$get_dt_p = $this->db->get();
			if($get_dt_p->num_rows() > 0){
				foreach($get_dt_p->result_array() as $dtP){
					$dt_payment_name[$dtP['id']] = strtoupper($dtP['payment_type_name']);
				}
			}
			$payment_data = $dt_payment_name;
			
			//BANK DATA
			$dt_bank_payment = array();
			$dt_bank_name = array();
			$this->db->select('*');
			$this->db->from($this->prefix.'bank');
			$get_dt_b = $this->db->get();
			if($get_dt_b->num_rows() > 0){
				foreach($get_dt_b->result_array() as $dtP){
					$dt_bank_name[$dtP['id']] = strtoupper($dtP['bank_name']);
					
					if(empty($dt_bank_payment[$dtP['payment_id']])){
						$dt_bank_payment[$dtP['payment_id']] = array();
					}
					
					if(!in_array($dtP['id'], $dt_bank_payment[$dtP['payment_id']])){
						$dt_bank_payment[$dtP['payment_id']][] = $dtP['id'];
					}
					
				}
			}
			$bank_data = $dt_bank_payment;
			$bank_name_data = $dt_bank_name;
			
			
			//echo $this->db->last_query();
			$data_diskon_awal = array();
			$konversi_pembulatan_billing = array();
			$balancing_discount_billing = array();
			$all_product_data = array();
			$newData = array();
			$total_qty_billing = array();
			$total_dp_billing = array();
			$total_payment_billing = array();
			$total_bank_billing = array();
			$payment_billing_id = array();
			$no = 1;
			if(!empty($data_post['report_data'])){
				foreach ($data_post['report_data'] as $s){
					
					//update-0120.001
					if(!empty($shift_billing) AND empty($data_post['user_shift'])){
						if(!empty($s['nama_shift'])){
							$data_post['user_shift'] = $s['nama_shift'];
						}
					}
					
					if(empty($display_discount_type[$s['diskon_sebelum_pajak_service']])){
						$display_discount_type[$s['diskon_sebelum_pajak_service']] = array();
					}
					if(!in_array($s['billing_id'], $display_discount_type[$s['diskon_sebelum_pajak_service']])){
						$display_discount_type[$s['diskon_sebelum_pajak_service']][] = $s['billing_id'];
					}
					
					$s['item_no'] = $no;
					
					if(empty($all_product_data[$s['product_id']])){
						
						$all_product_data[$s['product_id']] = array();
						
					}
					
					
					$date_exp = explode(" ",$s['payment_date']);
					$date_exp2 = explode("-",$date_exp[0]);
					$s['billing_date'] = $date_exp2[2]."/".$date_exp2[1]."/".$date_exp2[0];
					
					//STILL ON CURR DAY
					$date_exp_time = explode(":",$date_exp[1]);
					if($date_exp_time[0] < 7){
						//billing date -1
						$datemin1 = strtotime($date_exp2[2]."-".$date_exp2[1]."-".$date_exp2[0]." ".$date_exp[1])-ONE_DAY_UNIX;
						$s['billing_date'] = date("d/m/Y",$datemin1);
					}
					
					//update-2001.002
					$get_bill_no = substr($s['billing_no'],0,6);
					$get_payment_Y = substr($s['billing_no'],0,2);
					$get_payment_m = substr($s['billing_no'],2,2);
					$get_payment_d = substr($s['billing_no'],4,2);
					$s['billing_date'] = $get_payment_d.'/'.$get_payment_m.'/'.($get_payment_Y+2000);
					
					if(empty($total_qty_billing[$s['billing_date']])){
						$total_qty_billing[$s['billing_date']] = array();
					}
					
					if(!in_array($s['billing_no'], $total_qty_billing[$s['billing_date']])){
						$total_qty_billing[$s['billing_date']][] = $s['billing_no'];
					}
					
					//update-2001.002					
					if(empty($total_payment_billing[$s['billing_date']])){
						$total_payment_billing[$s['billing_date']] = array();
						
						foreach($payment_data as $key_id => $payment_name){
							$total_payment_billing[$s['billing_date']][$key_id] = 0;
						}
					}								
					if(empty($total_bank_billing[$s['billing_date']])){
						$total_bank_billing[$s['billing_date']] = array();
						
						foreach($bank_name_data as $bank_id => $bank_name){
							$total_bank_billing[$s['billing_date']][$bank_id] = 0;
						}
					}				
					if(empty($total_dp_billing[$s['billing_date']])){
						$total_dp_billing[$s['billing_date']] = array();
					}
					if(empty($total_dp_billing[$s['billing_date']][$s['billing_no']])){
						$total_dp_billing[$s['billing_date']][$s['billing_no']] = $s['total_dp'];
					}
					
					if(empty($all_product_data[$s['product_id']][$s['billing_date']])){
						$all_product_data[$s['product_id']][$s['billing_date']] = array(
							'product_id'	=> $s['product_id'],
							'product_name'	=> $s['product_name'],
							'product_group'	=> $s['product_group'],
							'category_id'	=> $s['category_id'],
							'category_name'	=> $s['category_name'],
							'billing_date'	=> $s['billing_date'],
							'billing_no'	=> $s['billing_no'],
							'total_qty'	=> 0,
							'total_billing'	=> 0,
							'total_billing_show'	=> 0,
							'sub_total'	=> 0,
							'sub_total_show'	=> 0,
							'net_sales_total'	=> 0,
							'net_sales_total_show'	=> 0,
							'grand_total'	=> 0,
							'grand_total_show'	=> 0,
							'tax_total'	=> 0,
							'tax_total_show'	=> 0,
							'total_pembulatan'	=> 0,
							'total_pembulatan_show'	=> 0,
							'service_total'	=> 0,
							'service_total_show'	=> 0,
							'discount_total'	=> 0,
							'discount_total_show'	=> 0,
							'discount_billing_total'	=> 0,
							'discount_billing_total_show'	=> 0,
							'total_hpp'	=> 0,
							'total_hpp_show'	=> 0,
							'total_profit'	=> 0,
							'total_profit_show'	=> 0,
							'is_takeaway'	=> 0,
							'is_compliment'	=> 0,
							'compliment_total'	=> 0,
							'discount_total_before'	=> 0,
							'discount_total_before_show'	=> 0,
							'discount_billing_total_before'	=> 0,
							'discount_billing_total_before_show'	=> 0,
							'discount_total_after'	=> 0,
							'discount_total_after_show'	=> 0,
							'discount_billing_total_after'	=> 0,
							'discount_billing_total_after_show'	=> 0,
						);
						
					}
					
					
					$no++;
					
					
					$all_product_data[$s['product_id']][$s['billing_date']]['total_qty'] += $s['order_qty'];
					
					//CHECK IF INCLUDE TAX AND SERVICE
					$is_include = false;
					$all_percentage = 100;
					if($s['include_tax'] == 1){
						$is_include = true;
						$all_percentage += $s['tax_percentage'];
					}
					
					if($s['include_service'] == 1){
						$is_include = true;		
						$all_percentage += $s['service_percentage'];		
					}
					
					$grand_total_order = 0;
					if(!empty($s['is_compliment'])){
						$s['service_total'] = 0;
						$s['tax_total'] = 0;
					}
					
					$include_tax = $s['include_tax'];
					$include_service = $s['include_service'];
					$tax_percentage = $s['tax_percentage'];
					$service_percentage = $s['service_percentage'];
					$tax_total = 0;
					$service_total = 0;
					$product_price_real = 0;
					$total_billing_order = 0;
					$tax_total_order = 0;
					$service_total_order = 0;
					$sub_total = 0;
					$net_sales_total = 0;
					$is_balanced = false;
					
					if(!empty($include_tax) OR !empty($include_service)){
						
						//AUTOFIX-BUGS 1 Jan 2018
						if((!empty($include_tax) AND empty($include_service)) OR (empty($include_tax) AND !empty($include_service))){
							if($s['product_price'] != ($s['product_price_real']+$s['tax_total']+$s['service_total'])){
								$s['product_price_real'] = priceFormat(($s['product_price']/($all_percentage/100)), 0, ".", "");
							}
						}
						
						if(!empty($s['is_compliment'])){
							//$s['product_price_real'] = $s['product_price'];
							$s['tax_total'] = 0;
							$s['service_total'] = 0;
						}
						
						$total_billing_order = ($s['product_price_real']*$s['order_qty']);
						$tax_total_order = $s['tax_total'];
						$service_total_order = $s['service_total'];
						
						if($s['diskon_sebelum_pajak_service'] == 1){
							
							//$all_product_data[$s['product_id']][$s['billing_date']]['grand_total'] += ($s['product_price_real']*$s['order_qty']) - $s['discount_total'];
							//$grand_total_order = ($s['product_price_real']*$s['order_qty']) - $s['discount_total'];
							
							$sub_total = ($s['product_price_real']*$s['order_qty']);
							$sub_total += $s['tax_total'];
							$sub_total += $s['service_total'];
							
							$grand_total_order = $sub_total;
							$sub_total -= $s['discount_total'];
							
						}else{
							
							//$all_product_data[$s['product_id']][$s['billing_date']]['grand_total'] += ($s['product_price_real']*$s['order_qty']);
							//$grand_total_order = ($s['product_price_real']*$s['order_qty']);
							
							$sub_total = ($s['product_price_real']*$s['order_qty']);
							$sub_total += $s['tax_total'];
							$sub_total += $s['service_total'];
							
							$grand_total_order = $sub_total;
							//$sub_total -= $s['discount_total'];
							$is_balanced = true;
							
						}
						
						//update-2001.002
						$net_sales_total = $total_billing_order - $s['discount_total'];
					
						//$all_product_data[$s['product_id']][$s['billing_date']]['total_billing'] += ($s['product_price_real']*$s['order_qty']);
						//$all_product_data[$s['product_id']][$s['billing_date']]['tax_total'] += $s['tax_total'];
						//$all_product_data[$s['product_id']][$s['billing_date']]['service_total'] += $s['service_total'];
						
					}else
					{
							
						$total_billing_order = ($s['product_price']*$s['order_qty']);
						$tax_total_order = $s['tax_total'];
						$service_total_order = $s['service_total'];
						
						if($s['diskon_sebelum_pajak_service'] == 1){
							
							//$all_product_data[$s['product_id']][$s['billing_date']]['grand_total'] += ($s['product_price']*$s['order_qty']) - $s['discount_total'];
							//$grand_total_order = ($s['product_price']*$s['order_qty']) - $s['discount_total'];
							$sub_total = ($s['product_price']*$s['order_qty']);
							$sub_total += $s['tax_total'];
							$sub_total += $s['service_total'];
							
							$grand_total_order = $sub_total;
							$sub_total -= $s['discount_total'];
							
							//update-2001.002
							//$net_sales_total = $total_billing_order - $s['discount_total'];
						
						}else{
							
							//$all_product_data[$s['product_id']][$s['billing_date']]['grand_total'] += ($s['product_price']*$s['order_qty']);
							//$grand_total_order = ($s['product_price']*$s['order_qty']);
							$sub_total = ($s['product_price']*$s['order_qty']);
							$sub_total += $s['tax_total'];
							$sub_total += $s['service_total'];
							
							$grand_total_order = $sub_total;
							$sub_total -= $s['discount_total'];
							
							//update-2001.002
							//$net_sales_total = $total_billing_order - $s['discount_total'];
						
						}
						
						//update-2001.002
						$net_sales_total = $total_billing_order - $s['discount_total'];
					
						//$all_product_data[$s['product_id']][$s['billing_date']]['total_billing'] += ($s['product_price']*$s['order_qty']);
						//$all_product_data[$s['product_id']][$s['billing_date']]['tax_total'] += $s['tax_total'];
						//$all_product_data[$s['product_id']][$s['billing_date']]['service_total'] += $s['service_total'];
						
					}
					
					if(empty($data_diskon_awal[$s['product_id']])){
						$data_diskon_awal[$s['product_id']] = array();
					}
					
					if(empty($data_diskon_awal[$s['product_id']][$s['billing_date']])){
						$data_diskon_awal[$s['product_id']][$s['billing_date']] = array(
							'item'	=> 0,
							'billing'	=> 0,
							'item_before'	=> 0,
							'billing_before'	=> 0,
							'item_after'	=> 0,
							'billing_after'	=> 0,
						);
					}
					
					//cek if discount is disc billing
					$total_discount_product = 0;
					if($s['discount_perbilling'] == 1){
						
						$get_percentage = $s['billing_discount_percentage'];
						$sub_total_detail = ($s['product_price']*$s['order_qty']);
						
						$s['discount_total'] = priceFormat(($total_billing_order*($get_percentage/100)), 0, ".", "");
						
						if(empty($s['billing_discount_percentage']) OR $s['billing_discount_percentage'] == '0.00'){
							//persentase dr total billing
							$get_percentage = ($sub_total_detail / $s['grand_total']) * 100;
							$get_percentage = number_format($get_percentage,2,'.','');
							$s['discount_total'] = priceFormat(($s['billing_discount_total']*($get_percentage/100)), 0, ".", "");
						}
						
						$all_product_data[$s['product_id']][$s['billing_date']]['discount_billing_total'] += ($s['discount_total']);
						$total_discount_product = ($s['discount_total']);
						
						$data_diskon_awal[$s['product_id']][$s['billing_date']]['billing'] += $total_discount_product;
						if($s['diskon_sebelum_pajak_service'] == 1){
							$data_diskon_awal[$s['product_id']][$s['billing_date']]['billing_before'] += $total_discount_product;
							$all_product_data[$s['product_id']][$s['billing_date']]['discount_billing_total_before'] += $s['discount_total'];
						}else{
							$data_diskon_awal[$s['product_id']][$s['billing_date']]['billing_after'] += $total_discount_product;
							$all_product_data[$s['product_id']][$s['billing_date']]['discount_billing_total_after'] += $s['discount_total'];
						}
						
					}else{
						$all_product_data[$s['product_id']][$s['billing_date']]['discount_total'] += $s['discount_total'];
						$total_discount_product = ($s['discount_total']);
						
						$data_diskon_awal[$s['product_id']][$s['billing_date']]['item'] += $total_discount_product;
						if($s['diskon_sebelum_pajak_service'] == 1){
							$data_diskon_awal[$s['product_id']][$s['billing_date']]['item_before'] += $total_discount_product;
							$all_product_data[$s['product_id']][$s['billing_date']]['discount_total_before'] += $s['discount_total'];
						}else{
							$data_diskon_awal[$s['product_id']][$s['billing_date']]['item_after'] += $total_discount_product;
							$all_product_data[$s['product_id']][$s['billing_date']]['discount_total_after'] += $s['discount_total'];
						}
					}
					
					//BALANCING TOTAL BILLING
					if($s['free_item'] == 1){
						if(!empty($include_tax) OR !empty($include_service)){
							$total_billing_order = ($s['product_price_real']*$s['order_qty']); 
						}else{
							$total_billing_order = ($s['product_price']*$s['order_qty']); 
						}
						
						$grand_total_order = $s['discount_total'];
						$total_billing = $grand_total_order;
					}else{
						$total_billing = $total_billing_order;
					}
					
					$all_product_data[$s['product_id']][$s['billing_date']]['total_hpp'] += ($s['product_price_hpp']*$s['order_qty']);
					$all_product_data[$s['product_id']][$s['billing_date']]['total_billing'] += $total_billing_order;
					$all_product_data[$s['product_id']][$s['billing_date']]['tax_total'] += $tax_total_order;
					$all_product_data[$s['product_id']][$s['billing_date']]['service_total'] += $service_total_order;
					
					$skip_balancing = false;
					
					//COMPLIMENT
					if(!empty($s['is_compliment'])){
						
						$compliment_total = $grand_total_order;
						$all_product_data[$s['product_id']][$s['billing_date']]['compliment_total'] += $compliment_total;
						$all_product_data[$s['product_id']][$s['billing_date']]['is_compliment'] = 1;
						
						$s['service_total'] = 0;
						$s['tax_total'] = 0;
						$sub_total = 0;
						$net_sales_total = 0;
						$grand_total_order = 0;
						$skip_balancing = true;
						
					}
					
					
					$all_product_data[$s['product_id']][$s['billing_date']]['net_sales_total'] += $net_sales_total;
					$all_product_data[$s['product_id']][$s['billing_date']]['sub_total'] += $sub_total;
					$all_product_data[$s['product_id']][$s['billing_date']]['grand_total'] += $grand_total_order;
								
					//OVERRIDE PEMBULATAN PERITEM
					$total_pembulatan = 0;
					
					$all_product_data[$s['product_id']][$s['billing_date']]['total_pembulatan'] += $total_pembulatan;
					$all_product_data[$s['product_id']][$s['billing_date']]['grand_total'] += $total_pembulatan;
					
					$grand_total_order += $total_pembulatan;
					
					//update-2001.002
					if(!empty($s['payment_id']) AND !in_array($s['billing_id'], $payment_billing_id)){
						$payment_billing_id[] = $s['billing_id'];
						if(!empty($payment_data)){
							foreach($payment_data as $key_id => $dtPay){
						
								$tot_payment = 0;
								$tot_payment_show = 0;
								if($s['payment_id'] == $key_id){
									//$tot_payment = $s['grand_total'];
									//$tot_payment_show = $s['grand_total_show'];
									
									if($key_id == 2 OR $key_id == 3 OR $key_id == 4){
										$tot_payment = $s['total_credit'];	
									}else{
										$tot_payment = $s['total_cash'];	
									}
									
									$tot_payment_show = priceFormat($tot_payment);
									
									//credit half payment
									if(!empty($s['is_half_payment']) AND $key_id != 1){
										$tot_payment = $s['total_credit'];
										$tot_payment_show = priceFormat($s['total_credit']);
									}else{
										
										$tot_payment_show = priceFormat($tot_payment);	
									}
										
								}else{
									//cash
									if(!empty($s['is_half_payment']) AND $key_id == 1){
										$tot_payment = $s['total_cash'];
										$tot_payment_show = priceFormat($s['total_cash']);
									}
								}
						
								if(empty($grand_total_payment[$key_id])){
									$grand_total_payment[$key_id] = 0;
								}
						
								if(!empty($s['is_compliment'])){
									//$tot_payment = 0;
									//$tot_payment_show = 0;
								}
						
								if(!empty($s['discount_total']) AND !empty($tot_payment)){
									//$tot_payment = $tot_payment - $s['discount_total'];
									//$tot_payment_show = priceFormat($tot_payment);
								}
						
								//$grand_total_payment[$key_id] += $tot_payment;
								$total_payment_billing[$s['billing_date']][$key_id] += $tot_payment;
																
							}
						}
						
						if(!empty($s['bank_id'])){
							$total_bank_billing[$s['billing_date']][$s['bank_id']] += $s['total_credit'];
						}
					
					}
					
					//BALANCING DISKON
					if(!empty($s['billing_discount_total'])){
						if(empty($balancing_discount_billing[$s['billing_id']])){
							$balancing_discount_billing[$s['billing_id']] = array(
								'discount_total'	=> $s['billing_discount_total'],
								'discount_detail_total'	=> 0,
								'payment_id'	=> 0,
								'bank_id'	=> 0,
								'discount_perbilling'	=> $s['discount_perbilling'],
								'discount_detail'	=> array(),
								'billing_date'	=> $s['billing_date'],
								'diskon_sebelum_pajak_service' => $s['diskon_sebelum_pajak_service'],
								'payment_date' => $s['payment_date']
							);
						}
					}
					
					if(!empty($s['billing_discount_total'])){
						if(empty($balancing_discount_billing[$s['billing_id']]['discount_detail'][$s['product_id']])){
							$balancing_discount_billing[$s['billing_id']]['discount_detail'][$s['product_id']] = array(
								'total_discount'=> 0,
								'total_discount_balance'=> 0,
								'tax_total'	=> 0,
								'service_total'	=> 0,
								'total_billing'	=> 0,
								'sub_total'	=> 0,
								'net_sales_total'	=> 0,
								'sub_total_balance'=> 0,
								'discount_balance'=> 0
							);
						}
						$balancing_discount_billing[$s['billing_id']]['discount_detail'][$s['product_id']]['total_discount'] += $total_discount_product;
						$balancing_discount_billing[$s['billing_id']]['discount_detail'][$s['product_id']]['tax_total'] += $s['tax_total'];
						$balancing_discount_billing[$s['billing_id']]['discount_detail'][$s['product_id']]['service_total'] += $s['service_total'];
						$balancing_discount_billing[$s['billing_id']]['discount_detail'][$s['product_id']]['total_billing'] += $total_billing;
						$balancing_discount_billing[$s['billing_id']]['discount_detail'][$s['product_id']]['sub_total'] += $sub_total;
						$balancing_discount_billing[$s['billing_id']]['discount_detail'][$s['product_id']]['net_sales_total'] += $net_sales_total;
						$balancing_discount_billing[$s['billing_id']]['discount_detail_total'] += $total_discount_product;
						$balancing_discount_billing[$s['billing_id']]['payment_id'] = $s['payment_id'];
						$balancing_discount_billing[$s['billing_id']]['bank_id'] = $s['bank_id'];
						$balancing_discount_billing[$s['billing_id']]['sub_total'] += $sub_total;
						$balancing_discount_billing[$s['billing_id']]['net_sales_total'] += $net_sales_total;
					}
					
					if(!empty($total_billing)){
						//KONVERSI PEMBULATAN PER-ITEM
						if(empty($konversi_pembulatan_billing[$s['billing_id']])){
							$konversi_pembulatan_billing[$s['billing_id']] = array(
								'total_qty'	=> 0,
								'billing_total_pembulatan'	=> $s['billing_total_pembulatan'],
								'total_pembulatan_product'	=> array(),
								'billing_date'	=> $s['billing_date']
							);
						}
						
						$konversi_pembulatan_billing[$s['billing_id']]['total_qty'] += $s['order_qty'];
						if(empty($konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$s['product_id']])){
							$konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$s['product_id']] = array(
								'total_pembulatan'	=> 0,
								'payment'	=> array(),
								'bank'	=> array()
							);
						}
						$konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$s['product_id']]['total_pembulatan'] = $total_pembulatan;
						if(!empty($s['payment_id'])){
							if(empty($konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$s['product_id']]['payment'][$s['payment_id']])){
								$konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$s['product_id']]['payment'][$s['payment_id']] = 0;
							}
							$konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$s['product_id']]['payment'][$s['payment_id']] += $total_pembulatan;
						}
						
						//bank_id
						if(!empty($s['bank_id'])){
							if(empty($konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$s['product_id']]['bank'][$s['bank_id']])){
								$konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$s['product_id']]['bank'][$s['bank_id']] = 0;
							}
							$konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$s['product_id']]['bank'][$s['bank_id']] += $total_pembulatan;
						}
					}
				}
			}
			
			//PEMBAGIAN PEMBULATAN AVERAGE
			$konversi_pembulatan_product = array();
			$konversi_pembulatan_product_payment = array();
			$konversi_pembulatan_product_bank = array();
			$pembulatan_awal_product = array();
			$pembulatan_awal_product_payment = array();
			$pembulatan_awal_product_bank = array();
			if(!empty($konversi_pembulatan_billing)){
				foreach($konversi_pembulatan_billing as $dt){
					//if($dt['billing_total_pembulatan'] != 0){
						$pembagian_pembulatan = $dt['billing_total_pembulatan'] / count($dt['total_pembulatan_product']);
						
						$pembagian_pembulatan = number_format($pembagian_pembulatan, 2);
						
						//cek selisih
						$selisih_pembagian = $pembagian_pembulatan*count($dt['total_pembulatan_product']) - $dt['billing_total_pembulatan'];
						//echo ($pembagian_pembulatan*count($dt['total_pembulatan_product'])).' - '.$dt['billing_total_pembulatan'].' = '.$selisih_pembagian.'<br/>';
						$no = 1;
						foreach($dt['total_pembulatan_product'] as $product_id => $data){
							if(empty($konversi_pembulatan_product[$product_id])){
								$konversi_pembulatan_product[$product_id] = array(
									//'total_pembulatan' => 0
								);
							}
							if(empty($konversi_pembulatan_product[$product_id][$dt['billing_date']])){
								$konversi_pembulatan_product[$product_id][$dt['billing_date']] = array(
									'total_pembulatan' => 0
								);
							}
							if(empty($pembulatan_awal_product[$product_id])){
								$pembulatan_awal_product[$product_id] = array();
							}
							if(empty($pembulatan_awal_product[$product_id][$dt['billing_date']])){
								$pembulatan_awal_product[$product_id][$dt['billing_date']] = 0;
							}
							
							$pembulatan_awal_product[$product_id][$dt['billing_date']] += $data['total_pembulatan'];
							
							$konversi_pembulatan_product[$product_id][$dt['billing_date']]['total_pembulatan'] += $pembagian_pembulatan;
							if($no == 1 AND $selisih_pembagian != 0){
								$konversi_pembulatan_product[$product_id][$dt['billing_date']]['total_pembulatan'] -= $selisih_pembagian;
							}
							
							//PAYMENT
							if(!empty($data['payment'])){
								foreach($data['payment'] as $payment_id => $dtP){
									if(empty($konversi_pembulatan_product_payment[$product_id][$dt['billing_date']])){
										$konversi_pembulatan_product_payment[$product_id][$dt['billing_date']] = array();
									}
									if(empty($konversi_pembulatan_product_payment[$product_id][$dt['billing_date']][$payment_id])){
										$konversi_pembulatan_product_payment[$product_id][$dt['billing_date']][$payment_id] = 0;
									}
									$konversi_pembulatan_product_payment[$product_id][$dt['billing_date']][$payment_id] += $pembagian_pembulatan;
									if($no == 1 AND $selisih_pembagian != 0){
										$konversi_pembulatan_product_payment[$product_id][$dt['billing_date']][$payment_id] -= $selisih_pembagian;
									}
									
									if(empty($pembulatan_awal_product_payment[$product_id][$dt['billing_date']])){
										$pembulatan_awal_product_payment[$product_id][$dt['billing_date']] = array();
									}
									if(empty($pembulatan_awal_product_payment[$product_id][$dt['billing_date']][$payment_id])){
										$pembulatan_awal_product_payment[$product_id][$dt['billing_date']][$payment_id] = 0;
									}
									$pembulatan_awal_product_payment[$product_id][$dt['billing_date']][$payment_id] += $dtP;
									
									
								}
								
							}
							//$konversi_data = $data['total_pembulatan'] - $pembagian_pembulatan;
							
							//BANK
							if(!empty($data['bank'])){
								foreach($data['bank'] as $bank_id => $dtP){
									if(empty($konversi_pembulatan_product_bank[$product_id][$dt['billing_date']])){
										$konversi_pembulatan_product_bank[$product_id][$dt['billing_date']] = array();
									}
									if(empty($konversi_pembulatan_product_bank[$product_id][$dt['billing_date']][$bank_id])){
										$konversi_pembulatan_product_bank[$product_id][$dt['billing_date']][$bank_id] = 0;
									}
									$konversi_pembulatan_product_bank[$product_id][$dt['billing_date']][$bank_id] += $pembagian_pembulatan;
									if($no == 1 AND $selisih_pembagian != 0){
										$konversi_pembulatan_product_bank[$product_id][$dt['billing_date']][$bank_id] -= $selisih_pembagian;
									}
									
									if(empty($pembulatan_awal_product_bank[$product_id][$dt['billing_date']])){
										$pembulatan_awal_product_bank[$product_id][$dt['billing_date']] = array();
									}
									if(empty($pembulatan_awal_product_bank[$product_id][$dt['billing_date']][$bank_id])){
										$pembulatan_awal_product_bank[$product_id][$dt['billing_date']][$bank_id] = 0;
									}
									$pembulatan_awal_product_bank[$product_id][$dt['billing_date']][$bank_id] += $dtP;
									
									
								}
								
							}
							//$konversi_data = $data['total_pembulatan'] - $pembagian_pembulatan;
							
							$no++;
						}
					//}
				}
			}
			
			//BALANCING DISKON
			//$data_diskon_awal = array();
			$data_diskon_awal_payment = array();
			$data_diskon_awal_bank = array();
			$data_balancing_diskon = array();
			$data_balancing_diskon_payment = array();
			$data_balancing_diskon_bank = array();
			$data_selisih_diskon = array();
			$data_selisih_diskon_payment = array();
			$data_selisih_diskon_bank = array();
			if(!empty($balancing_discount_billing)){
				foreach($balancing_discount_billing as $billing_id => $dt){
					$selisih_diskon = $dt['discount_total'] - $dt['discount_detail_total'];
					$total_produk = count($dt['discount_detail']);
					
					//AVERAGE
					/*$selisih_diskon_perproduct = 0;
					if($selisih_diskon != 0){
						$selisih_diskon_perproduct = $selisih_diskon/$total_produk;
						$selisih_diskon_perproduct = number_format($selisih_diskon_perproduct, 2);
					}*/
					
					$discount_detail_total = 0;
					$discount_billing_detail_total = 0;
					
					if(!empty($dt['discount_detail'])){
						
						$no = 0;
						foreach($dt['discount_detail'] as $product_id => $dt_diskon){
							$no++;
							//$discount_detail_total += ($dt_diskon['total_discount']+$selisih_diskon_perproduct);
							
							//average
							$discount_billing_detail_total = $dt_diskon['total_discount'];
							
							//PERSENTASE DISKON - average by total billing percentage
							$total_disc_prod = 0;
							$persentase_disc_prod = 0;
							if($dt['discount_perbilling'] == 1){
								$total_disc_prod = 0;
								if(!empty($dt['total_billing'])){
									$persentase_disc_prod = ($dt_diskon['total_billing'] / $dt['total_billing']) * 100;
								}
								$persentase_disc_prod = priceFormat($persentase_disc_prod, 2, ".", "");
								$persentase_total_billing += $persentase_disc_prod;

								if($no == $total_produk){
									if($persentase_total_billing != 100){
										$persentase_disc_prod += (100 - $persentase_total_billing);
									}
								}

								$total_disc_prod = ($persentase_disc_prod*$dt['discount_total'])/100;

								//$discount_billing_detail_total += ($dt_diskon['total_discount']+$total_disc_prod);

								//DISCOUNT > total billing
								//echo '$total_disc_prod = '.$total_disc_prod.' > sub_total = '.$dt_diskon['sub_total'].'<br/>';
								if($total_disc_prod > $dt_diskon['sub_total']){
									//$total_disc_prod = $dt_diskon['sub_total'];
								}

								//$discount_billing_detail_total = ($dt_diskon['total_discount']+$total_disc_prod);
								$discount_billing_detail_total = $total_disc_prod;
							}
							
							$discount_detail_total += $discount_billing_detail_total;
							
							//if(empty($data_diskon_awal[$product_id])){
							//	$data_diskon_awal[$product_id] = array(
									//'item'	=> 0,
									//'billing'	=> 0
							//	);
							//}
							
							if(empty($data_balancing_diskon[$product_id])){
								$data_balancing_diskon[$product_id] = array(
									//'item'	=> 0,
									//'billing'	=> 0
								);
							}
							
							if(empty($data_balancing_diskon_payment[$product_id])){
								$data_balancing_diskon_payment[$product_id] = array();
							}
							if(empty($data_balancing_diskon_payment[$product_id][$dt['payment_id']])){
								$data_balancing_diskon_payment[$product_id][$dt['payment_id']] = array();
							}

							
							//if(empty($data_diskon_awal[$product_id][$dt['billing_date']])){
							//	$data_diskon_awal[$product_id][$dt['billing_date']] = array(
							//		'item'	=> 0,
							//		'billing'	=> 0
							//	);
							//}
							
							if(empty($data_balancing_diskon[$product_id][$dt['billing_date']])){
								$data_balancing_diskon[$product_id][$dt['billing_date']] = array(
									'item'	=> 0,
									'billing'	=> 0,
									'item_before'	=> 0,
									'billing_before'	=> 0,
									'item_after'	=> 0,
									'billing_after'	=> 0,
								);
							}
							
							
							//if($dt['discount_perbilling'] == 1){
							//	$data_diskon_awal[$product_id][$dt['billing_date']]['billing'] += $dt_diskon['total_discount'];
							//}else{
							//	$data_diskon_awal[$product_id][$dt['billing_date']]['item'] += $dt_diskon['total_discount'];
							//}
							
							if($dt['discount_perbilling'] == 1){
								//$data_balancing_diskon[$product_id][$dt['billing_date']]['billing'] += ($dt_diskon['total_discount']+$selisih_diskon_perproduct);
								$data_balancing_diskon[$product_id][$dt['billing_date']]['billing'] += $discount_billing_detail_total;
								$data_balancing_diskon_payment[$product_id][$dt['billing_date']][$dt['payment_id']] += $discount_billing_detail_total;
								
								if($dt['diskon_sebelum_pajak_service'] == 1){
									$data_balancing_diskon[$product_id][$dt['billing_date']]['billing_before'] += $discount_billing_detail_total;
								}else{
									$data_balancing_diskon[$product_id][$dt['billing_date']]['billing_after'] += $discount_billing_detail_total;
								}
								
							}else{
								//$data_balancing_diskon[$product_id][$dt['billing_date']]['item'] += ($dt_diskon['total_discount']+$selisih_diskon_perproduct);
								$data_balancing_diskon[$product_id][$dt['billing_date']]['item'] += $discount_billing_detail_total;
								$data_balancing_diskon_payment[$product_id][$dt['billing_date']][$dt['payment_id']] += $discount_billing_detail_total;
								
								if($dt['diskon_sebelum_pajak_service'] == 1){
									$data_balancing_diskon[$product_id][$dt['billing_date']]['item_before'] += $discount_billing_detail_total;
								}else{
									$data_balancing_diskon[$product_id][$dt['billing_date']]['item_after'] += $discount_billing_detail_total;
								}
							}
							
							//$balancing_discount_billing[$billing_id]['discount_detail'][$product_id]['total_discount_balance'] = ($dt_diskon['total_discount']+$selisih_diskon_perproduct);
							$balancing_discount_billing[$billing_id]['discount_detail'][$product_id]['total_discount_balance'] = $discount_billing_detail_total;
							
							/*if($no == count($dt['discount_detail'])){
								if($discount_detail_total != $dt['discount_total']){
									$selisih_akhir = $dt['discount_total'] - $discount_detail_total;
									
									if($dt['discount_perbilling'] == 1){
										$data_balancing_diskon[$product_id][$dt['billing_date']]['billing'] += $selisih_akhir;
									}else{
										$data_balancing_diskon[$product_id][$dt['billing_date']]['item'] += $selisih_akhir;
									}
									
									$balancing_discount_billing[$billing_id]['discount_detail'][$product_id]['total_discount_balance'] += $selisih_akhir;
									
								}
							}*/
							
						}
						
					}
				}
				
				//SET SELISIH DISKON
				if(!empty($balancing_discount_billing)){
					foreach($balancing_discount_billing as $billing_id => $dt){
						if(!empty($dt['discount_detail'])){
							foreach($dt['discount_detail'] as $product_id => $dt_diskon){
								
								//$sub_total_balance = $dt_diskon['total_billing'] - $dt_diskon['total_discount_balance'];
								$sub_total_balance = $dt_diskon['total_billing'];

								if($sub_total_balance <= 0){
									$sub_total_balance = 0;
								}else{
									$sub_total_balance += $dt_diskon['tax_total'];
									$sub_total_balance += $dt_diskon['service_total'];
								}
								
								$discount_detail_total += $sub_total_balance;
								//echo '$sub_total_balance = '.$sub_total_balance.'<br/>';

								//echo $product_id.' total_billing = '.$dt_diskon['total_billing'].' -  total_discount = '.$dt_diskon['total_discount'].', +tax_total = '.$dt_diskon['tax_total'].', +service_total = '.$dt_diskon['service_total'].' ==> sub_total_balance = '.$sub_total_balance.', discount_detail_total = '.$discount_detail_total.'<br/>';

								$balancing_discount_billing[$billing_id]['discount_detail'][$product_id]['sub_total_balance'] = $sub_total_balance;
								
								$sub_total_selisih = 0;
								//KONDISI SELISIH 1: sub_total > $sub_total_balance
								if($dt_diskon['sub_total'] > $sub_total_balance){
									//echo '$sub_total = '.$dt_diskon['sub_total'].' > $sub_total_balance = '.$sub_total_balance.'<br/>';
									$sub_total_selisih = $dt_diskon['sub_total'] - $sub_total_balance;
								}

								//KONDISI SELISIH 2: total_discount_balance > $sub_total_balance
								if($dt_diskon['total_discount_balance'] > $sub_total_balance){
									//echo '$total_discount_balance = '.$dt_diskon['total_discount_balance'].' > $sub_total_balance = '.$sub_total_balance.'<br/>';
									$sub_total_selisih = $sub_total_balance - $dt_diskon['total_discount_balance'];
								}
								
								$balancing_discount_billing[$billing_id]['discount_detail'][$product_id]['discount_balance'] = $sub_total_selisih;
								
								if(empty($data_selisih_diskon[$product_id])){
									$data_selisih_diskon[$product_id] = array();
								}
								if(empty($data_selisih_diskon[$product_id][$dt['billing_date']])){
									$data_selisih_diskon[$product_id][$dt['billing_date']] = 0;
								}
								
								$data_selisih_diskon[$product_id][$dt['billing_date']] += $sub_total_selisih;
								
								if(empty($data_selisih_diskon_payment[$product_id])){
									$data_selisih_diskon_payment[$product_id] = array();
								}
								if(empty($data_selisih_diskon_payment[$product_id][$dt['billing_date']])){
									$data_selisih_diskon_payment[$product_id][$dt['billing_date']] = array();
								}
								
								if(empty($data_selisih_diskon_payment[$product_id][$dt['billing_date']][$dt['payment_id']])){
									$data_selisih_diskon_payment[$product_id][$dt['billing_date']][$dt['payment_id']] = 0;
								}
								
								//echo $product_id.' -> '.$dt['payment_id'].' <br/>';
								$data_selisih_diskon_payment[$product_id][$dt['billing_date']][$dt['payment_id']] += $sub_total_selisih;
								
								if(empty($data_selisih_diskon_bank[$product_id])){
									$data_selisih_diskon_bank[$product_id] = array();
								}
								if(empty($data_selisih_diskon_bank[$product_id][$dt['billing_date']])){
									$data_selisih_diskon_bank[$product_id][$dt['billing_date']] = array();
								}
								
								if(empty($data_selisih_diskon_bank[$product_id][$dt['billing_date']][$dt['bank_id']])){
									$data_selisih_diskon_bank[$product_id][$dt['billing_date']][$dt['bank_id']] = 0;
								}
								
								//echo $product_id.' -> '.$dt['bank_id'].' <br/>';
								$data_selisih_diskon_bank[$product_id][$dt['billing_date']][$dt['bank_id']] += $sub_total_selisih;
								
							}
						}
					}
				}
			}
			
			
			//echo '<pre>';
			//print_r($all_product_data);
			//echo 'TOTAL = '.count($all_product_data).'<br/>';
			//echo '<br/>';
			//die();
			
			
			$sort_qty = array();
			$sort_profit = array();
			$no = 1;
			if(!empty($all_product_data)){
				foreach($all_product_data as $det){
					
					if(!empty($det)){
						foreach($det as $billing_date => $dt){
							$dt['item_no'] = $no;
					
							$sort_qty[$dt['product_id']] = $dt['total_qty'];
							
							//echo $billing_date.'<br/>';
							//echo 'grandtotal='.$dt['grand_total'].', discount_total = '.$dt['discount_total'].', discount_billing_total = '.$dt['discount_billing_total'].'<br/>';
							
							//BALANCING DISKON
							if(!empty($data_diskon_awal[$dt['product_id']][$billing_date])){
								$dt['discount_total'] -= $data_diskon_awal[$dt['product_id']][$billing_date]['item'];
								$dt['discount_billing_total'] -= $data_diskon_awal[$dt['product_id']][$billing_date]['billing'];
								//echo 'data_diskon_awal, item_before='.$data_diskon_awal[$dt['product_id']][$billing_date]['item_before'].', item_after='.$data_diskon_awal[$dt['product_id']][$billing_date]['item_after'].'<br/>';
								
								//before&after
								$dt['discount_total_before'] -= $data_diskon_awal[$dt['product_id']][$billing_date]['item_before'];
								$dt['discount_billing_total_before'] -= $data_diskon_awal[$dt['product_id']][$billing_date]['billing_before'];
								$dt['discount_total_after'] -= $data_diskon_awal[$dt['product_id']][$billing_date]['item_after'];
								$dt['discount_billing_total_after'] -= $data_diskon_awal[$dt['product_id']][$billing_date]['billing_after'];
							}
							
							if(!empty($data_balancing_diskon[$dt['product_id']][$billing_date])){
								$dt['discount_total'] += $data_balancing_diskon[$dt['product_id']][$billing_date]['item'];
								$dt['discount_billing_total'] += $data_balancing_diskon[$dt['product_id']][$billing_date]['billing'];
								//echo 'data_balancing_diskon, item='.$data_balancing_diskon[$dt['product_id']][$billing_date]['item_before'].', item_after='.$data_balancing_diskon[$dt['product_id']][$billing_date]['item_after'].'<br/>';
								
								//before&after
								$dt['discount_total_before'] += $data_balancing_diskon[$dt['product_id']][$billing_date]['item_before'];
								$dt['discount_billing_total_before'] += $data_balancing_diskon[$dt['product_id']][$billing_date]['billing_before'];
								$dt['discount_total_after'] += $data_balancing_diskon[$dt['product_id']][$billing_date]['item_after'];
								$dt['discount_billing_total_after'] += $data_balancing_diskon[$dt['product_id']][$billing_date]['billing_after'];
							}
							
							//exclude tax service
							$dt['grand_total'] -=$dt['discount_total'];
							$dt['grand_total'] -=$dt['discount_billing_total'];

							//echo 'grandtotal='.$dt['grand_total'].', discount_total_before = '.$dt['discount_total_before'].', discount_total_after = '.$dt['discount_total_after'].'<br/>';
							
							if(!empty($data_selisih_diskon[$dt['product_id']][$billing_date])){
								//$dt['sub_total'] -= $data_selisih_diskon[$dt['product_id']][$billing_date];
								$dt['grand_total'] -= $data_selisih_diskon[$dt['product_id']][$billing_date];
							}
							
							//BALANCING DISKON PAYMENT
							if(!empty($data_balancing_diskon_payment[$dt['product_id']][$billing_date])){
								foreach($data_balancing_diskon_payment[$dt['product_id']][$billing_date] as $payment_id => $dtP){
									if(!empty($dt['payment_'.$payment_id])){
										$dt['payment_'.$payment_id] -= $dtP;
										//echo 'BALANCING DISKON PAYMENT -= '.$dtP.'<br/>';
									}
								}
							}
							if(!empty($data_selisih_diskon_payment[$dt['product_id']][$billing_date])){
								foreach($data_selisih_diskon_payment[$dt['product_id']][$billing_date] as $payment_id => $dtP){
									if(!empty($dt['payment_'.$payment_id])){
										$dt['payment_'.$payment_id] -= $dtP;
										//echo 'SELISIH DISKON PAYMENT -= '.$dtP.'<br/>';
									}
								}
							}
							
							//BALANCING DISKON BANK
							if(!empty($data_selisih_diskon_bank[$dt['product_id']][$billing_date])){
								foreach($data_selisih_diskon_bank[$dt['product_id']][$billing_date] as $bank_id => $dtP){
									if(!empty($dt['bank_'.$bank_id])){
										$dt['bank_'.$bank_id] -= $dtP;
									}
								}
							}
							
							
							//KONVERSI PEMBULATAN
							$selisih_pembulatan = 0;
							if(!empty($pembulatan_awal_product[$dt['product_id']][$billing_date])){
								$selisih_pembulatan -= $pembulatan_awal_product[$dt['product_id']][$billing_date];
								$dt['grand_total'] -= $pembulatan_awal_product[$dt['product_id']][$billing_date];
							}
							
							
							if(!empty($konversi_pembulatan_product[$dt['product_id']][$billing_date])){
								$dt['total_pembulatan'] = $konversi_pembulatan_product[$dt['product_id']][$billing_date]['total_pembulatan'];
								$dt['grand_total'] += $konversi_pembulatan_product[$dt['product_id']][$billing_date]['total_pembulatan'];
								$selisih_pembulatan += $konversi_pembulatan_product[$dt['product_id']][$billing_date]['total_pembulatan'];
							}
							
							if(!empty($dt['compliment_total'])){
								//$dt['compliment_total'] += $selisih_pembulatan;
							}
							
							//KONVERSI PEMBULATAN PAYMENT
							if(!empty($pembulatan_awal_product_payment[$dt['product_id']][$billing_date])){
								foreach($pembulatan_awal_product_payment[$dt['product_id']][$billing_date] as $payment_id => $dtP){
									if(!empty($dt['payment_'.$payment_id])){
										$dt['payment_'.$payment_id] -= $dtP;
									}
								}
							}
							
							if(!empty($konversi_pembulatan_product_payment[$dt['product_id']][$billing_date])){
								foreach($konversi_pembulatan_product_payment[$dt['product_id']][$billing_date] as $payment_id => $dtP){
									if(!empty($dt['payment_'.$payment_id])){
										$dt['payment_'.$payment_id] += $dtP;
									}
								}
							}
							
							
							//KONVERSI PEMBULATAN BANK
							if(!empty($pembulatan_awal_product_bank[$dt['product_id']][$billing_date])){
								foreach($pembulatan_awal_product_bank[$dt['product_id']][$billing_date] as $bank_id => $dtP){
									if(!empty($dt['bank_'.$bank_id])){
										$dt['bank_'.$bank_id] -= $dtP;
									}
								}
							}
							
							if(!empty($konversi_pembulatan_product_bank[$dt['product_id']][$billing_date])){
								foreach($konversi_pembulatan_product_bank[$dt['product_id']][$billing_date] as $bank_id => $dtP){
									if(!empty($dt['bank_'.$bank_id])){
										$dt['bank_'.$bank_id] += $dtP;
									}
								}
							}
							
							
							$dt['total_billing_show'] = priceFormat($dt['total_billing']);
							$dt['grand_total_show'] = priceFormat($dt['grand_total']);
							$dt['sub_total_show'] = priceFormat($dt['sub_total']);
							$dt['net_sales_total_show'] = priceFormat($dt['net_sales_total']);
							$dt['tax_total_show'] = priceFormat($dt['tax_total']);
							$dt['service_total_show'] = priceFormat($dt['service_total']);
							
							$dt['total_pembulatan_show'] = priceFormat($dt['total_pembulatan']);
							$dt['discount_total_show'] = priceFormat($dt['discount_total']);
							$dt['discount_billing_total_show'] = priceFormat($dt['discount_billing_total']);
							
							$dt['discount_total_before_show'] = priceFormat($dt['discount_total_before']);
							$dt['discount_billing_total_before_show'] = priceFormat($dt['discount_billing_total_before']);
							$dt['discount_total_after_show'] = priceFormat($dt['discount_total_after']);
							$dt['discount_billing_total_after_show'] = priceFormat($dt['discount_billing_total_after']);
							
							$dt['compliment_total_show'] = priceFormat($dt['compliment_total']);
							$dt['total_compliment'] = $dt['compliment_total'];
							$dt['total_compliment_show'] = priceFormat($dt['compliment_total']);
							
							$dt['total_profit'] = $dt['total_billing']-$dt['total_hpp'];
							$dt['total_hpp_show'] = priceFormat($dt['total_hpp']);
							$dt['total_profit_show'] = priceFormat($dt['total_profit']);
							$sort_profit[$dt['product_id']] = $dt['total_profit'];
							
							if(empty($newData[$dt['product_id']])){
								$newData[$dt['product_id']] = array();
							}
												
							$newData[$dt['product_id']][$billing_date] = $dt;
							$no++;
						}
					}
					
					
				}
			}
			

			
			//echo '<pre>';
			//print_r($newData);
			//echo 'TOTAL = '.count($newData);
			//die();
			
		
			//GROUPING
			$total_dp_perday = array();
			$payment_perday = array();
			$bank_perday = array();
			$total_payment_perday = array();
			$total_bank_perday = array();
			$new_GroupData = array();
			if(!empty($newData)){
				foreach($newData as $det){
					
					if(!empty($det)){
						foreach($det as $dt){
							if(empty($new_GroupData[$dt['category_name']])){
								$new_GroupData[$dt['category_name']] = array();
							}
							
							$tgl_created = $dt['billing_date'];
							
							if(empty($new_GroupData[$dt['category_name']][$tgl_created])){
								$new_GroupData[$dt['category_name']][$tgl_created] = array(
									'total_qty'	=> 0,
									'total_billing'	=> 0,
									'sub_total'	=> 0,
									'net_sales_total'	=> 0,
									'grand_total'	=> 0,
									'tax_total'	=> 0,
									'total_pembulatan'	=> 0,
									'service_total'	=> 0,
									'discount_total'	=> 0,
									'discount_billing_total'	=> 0,
									'total_hpp'	=> 0,
									'total_profit'	=> 0,
									'compliment_total'	=> 0,
									'discount_total_before'	=> 0,
									'discount_total_before_show'	=> 0,
									'discount_billing_total_before'	=> 0,
									'discount_billing_total_before_show'	=> 0,
									'discount_total_after'	=> 0,
									'discount_total_after_show'	=> 0,
									'discount_billing_total_after'	=> 0,
									'discount_billing_total_after_show'	=> 0,
								);
							}	
							
							$new_GroupData[$dt['category_name']][$tgl_created]['total_qty'] += $dt['total_qty'];
							$new_GroupData[$dt['category_name']][$tgl_created]['total_billing'] += $dt['total_billing'];
							$new_GroupData[$dt['category_name']][$tgl_created]['sub_total'] += $dt['sub_total'];
							$new_GroupData[$dt['category_name']][$tgl_created]['net_sales_total'] += $dt['net_sales_total'];
							$new_GroupData[$dt['category_name']][$tgl_created]['grand_total'] += $dt['grand_total'];
							$new_GroupData[$dt['category_name']][$tgl_created]['tax_total'] += $dt['tax_total'];
							$new_GroupData[$dt['category_name']][$tgl_created]['total_pembulatan'] += $dt['total_pembulatan'];
							$new_GroupData[$dt['category_name']][$tgl_created]['service_total'] += $dt['service_total'];
							$new_GroupData[$dt['category_name']][$tgl_created]['discount_total'] += $dt['discount_total'];
							$new_GroupData[$dt['category_name']][$tgl_created]['discount_billing_total'] += $dt['discount_billing_total'];
							$new_GroupData[$dt['category_name']][$tgl_created]['total_hpp'] += $dt['total_hpp'];
							$new_GroupData[$dt['category_name']][$tgl_created]['total_profit'] += $dt['total_profit'];
							$new_GroupData[$dt['category_name']][$tgl_created]['compliment_total'] += $dt['compliment_total'];
							
							$new_GroupData[$dt['category_name']][$tgl_created]['discount_total_before'] += $dt['discount_total_before'];
							$new_GroupData[$dt['category_name']][$tgl_created]['discount_billing_total_before'] += $dt['discount_billing_total_before'];
							$new_GroupData[$dt['category_name']][$tgl_created]['discount_total_after'] += $dt['discount_total_after'];
							$new_GroupData[$dt['category_name']][$tgl_created]['discount_billing_total_after'] += $dt['discount_billing_total_after'];
							
							/*foreach($dt_payment_name as $payment_id => $payment_name){
								if(empty($new_GroupData[$dt['category_name']][$tgl_created]['payment_'.$payment_id])){
									$new_GroupData[$dt['category_name']][$tgl_created]['payment_'.$payment_id] = 0;
								}
								
								if(!empty($dt['payment_'.$payment_id])){
									$new_GroupData[$dt['category_name']][$tgl_created]['payment_'.$payment_id] += $dt['payment_'.$payment_id];
									
									if(empty($payment_perday[$payment_id])){
										$payment_perday[$payment_id] = array();
									}
									if(empty($payment_perday[$payment_id][$tgl_created])){
										$payment_perday[$payment_id][$tgl_created] = 0;
									}
									$payment_perday[$payment_id][$tgl_created] += $dt['payment_'.$payment_id];
									
									if(empty($total_payment_perday[$tgl_created])){
										$total_payment_perday[$tgl_created] = 0;
									}
									$total_payment_perday[$tgl_created] += $dt['payment_'.$payment_id];
									
								}
								
							}*/
							
							if(empty($total_payment_perday[$tgl_created])){
								$total_payment_perday[$tgl_created] = 0;
								if(!empty($total_payment_billing[$tgl_created])){
									foreach($total_payment_billing[$tgl_created] as $payment_id => $total_payment){
										
										if(empty($payment_perday[$payment_id])){
											$payment_perday[$payment_id] = array();
										}
										if(empty($payment_perday[$payment_id][$tgl_created])){
											$payment_perday[$payment_id][$tgl_created] = 0;
										}
										$payment_perday[$payment_id][$tgl_created] += $total_payment;
										
										if(empty($total_payment_perday[$tgl_created])){
											$total_payment_perday[$tgl_created] = 0;
										}
										$total_payment_perday[$tgl_created] += $total_payment;
										
									}
								}
							}
							
							if(empty($total_dp_perday[$tgl_created])){
								$total_dp_perday[$tgl_created] = 0;
								if(!empty($total_dp_billing[$tgl_created])){
									foreach($total_dp_billing[$tgl_created] as $total_dp){
										$total_dp_perday[$tgl_created] += $total_dp;
										$total_payment_perday[$tgl_created] += $total_dp;
									}
								}
							}
							
							if(empty($total_bank_perday[$tgl_created])){
								$total_bank_perday[$tgl_created] = 0;
								if(!empty($total_bank_billing[$tgl_created])){
									foreach($total_bank_billing[$tgl_created] as $bank_id => $total_payment){
										
										if(empty($bank_perday[$bank_id])){
											$bank_perday[$bank_id] = array();
										}
										if(empty($bank_perday[$bank_id][$tgl_created])){
											$bank_perday[$bank_id][$tgl_created] = 0;
										}
										$bank_perday[$bank_id][$tgl_created] += $total_payment;
										
										if(empty($total_bank_perday[$tgl_created])){
											$total_bank_perday[$tgl_created] = 0;
										}
										$total_bank_perday[$tgl_created] += $total_payment;
										
									}
								}
							}
							
							/*
							foreach($dt_bank_payment as $payment_id => $dt_bank){
								
								if(!empty($dt_bank)){
									foreach($dt_bank as $bank_id){
										if(empty($new_GroupData[$dt['category_name']][$tgl_created]['bank_'.$bank_id])){
											$new_GroupData[$dt['category_name']][$tgl_created]['bank_'.$bank_id] = 0;
										}
										
										if(!empty($dt['bank_'.$bank_id])){
											$new_GroupData[$dt['category_name']][$tgl_created]['bank_'.$bank_id] += $dt['bank_'.$bank_id];
											
											
											if(empty($bank_perday[$bank_id])){
												$bank_perday[$bank_id] = array();
											}
											if(empty($bank_perday[$bank_id][$tgl_created])){
												$bank_perday[$bank_id][$tgl_created] = 0;
											}
											$bank_perday[$bank_id][$tgl_created] += $dt['bank_'.$bank_id];
											
											if(empty($total_bank_perday[$tgl_created])){
												$total_bank_perday[$tgl_created] = 0;
											}
											$total_bank_perday[$tgl_created] += $dt['bank_'.$bank_id];
											
											
										}
									}
								}
								
							}
							*/
							
						}
					}
					
				}
				
				ksort($new_GroupData);
				$newData = $new_GroupData;
			}
			
			//echo '<pre>';
			//print_r($total_dp_billing);
			//echo 'TOTAL = '.count($total_dp_billing);
			//die();
			
			$data_post['report_data'] = $newData;
			$data_post['total_qty_billing'] = $total_qty_billing;
			$data_post['total_dp_perday'] = $total_dp_perday;
			$data_post['payment_data'] = $dt_payment_name;
			$data_post['payment_perday'] = $payment_perday;
			$data_post['total_payment_perday'] = $total_payment_perday;
			$data_post['dt_bank_payment'] = $dt_bank_payment;
			$data_post['dt_bank_name'] = $dt_bank_name;
			$data_post['bank_perday'] = $bank_perday;
			$data_post['total_bank_perday'] = $total_bank_perday;
			$data_post['display_discount_type'] = $display_discount_type;
						
		}
		
		
		//DO-PRINT
		if(!empty($do)){
			$data_post['do'] = $do;
		}else{
			$do = '';
		}

		if(empty($useview)){
			$useview = 'print_salesSettlementReport';
			$data_post['report_name'] = 'SALES REPORT BY MENU';
			
			if($do == 'excel'){
				$useview = 'excel_salesSettlementReport';
			}
			
		}else{
			$useview = 'print_reportProfitSalesByMenu';
			$data_post['report_name'] = 'SALES PROFIT REPORT MENU';
			
			if($do == 'excel'){
				$useview = 'excel_reportProfitSalesByMenu';
			}
			
		}

		if(!empty($groupCat)){
			
			$useview = 'print_salesSettlementReport';
			$data_post['report_name'] = 'SALES SETTLEMENT REPORT';
			
			if($do == 'excel'){
				$useview = 'excel_salesSettlementReport';
			}
		}
		
		
		$this->load->view('../../billing/views/'.$useview, $data_post);
	}
	
}