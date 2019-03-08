<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class ReportSalesByFoodBeverage extends MY_Controller {
	
	public $table;
		
	function __construct()
	{
		parent::__construct();
		$this->prefix_apps = config_item('db_prefix');
		$this->prefix = config_item('db_prefix2');
		$this->load->model('model_databilling', 'm');
		$this->load->model('model_billingdetail', 'm2');
	}
	
	public function print_reportSalesByFoodBeverage(){
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
		
		$data_post = array(
			'do'	=> '',
			'report_data'	=> array(),
			'report_place_default'	=> '',
			'report_name'	=> 'SALES REPORT BY FOOD AND BEVERAGE',
			'date_from'	=> $date_from,
			'date_till'	=> $date_till,
			'cashier_name'	=> '',
			'user_fullname'	=> $user_fullname,
			'diskon_sebelum_pajak_service'	=> 0
		);
		
		if(empty($groupCat)){
			$groupCat = 0;
		}
		
		$get_opt = get_option_value(array('report_place_default','diskon_sebelum_pajak_service','cashier_max_pembulatan',
		'cashier_pembulatan_keatas','pembulatan_dinamis'));
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
						
			$qdate_from = date("Y-m-d",strtotime($date_from));
			$qdate_till = date("Y-m-d",strtotime($date_till));
			$qdate_till_max = date("Y-m-d",strtotime($date_till)+ONE_DAY_UNIX);
			
			$add_where = "(b.payment_date >= '".$qdate_from." 07:00:00' AND b.payment_date <= '".$qdate_till_max." 06:00:00')";
			
			/*
			b.tax_total, b.service_total,
			b.include_tax, b.tax_percentage, b.include_service, b.service_percentage,
			*/
			
			$this->db->select("a.*, b.billing_no, b.total_billing, b.discount_perbilling, b.payment_id, 
								b.discount_percentage as billing_discount_percentage, b.discount_total as billing_discount_total,
								b.total_pembulatan as billing_total_pembulatan,
								c.product_name, c.product_group, c.category_id, d.product_category_name as category_name");
			$this->db->from($this->table2." as a");
			$this->db->join($this->prefix.'billing as b','b.id = a.billing_id','LEFT');
			$this->db->join($this->prefix.'product as c','c.id = a.product_id','LEFT');
			$this->db->join($this->prefix.'product_category as d','d.id = c.category_id','LEFT');
			$this->db->where("(a.order_status != 'cancel' AND a.order_qty > 0)");	
			$this->db->where("a.is_deleted", 0);
			$this->db->where("b.is_deleted", 0);
			$this->db->where("b.billing_status", "paid");	
			$this->db->where($add_where);
			
			if(empty($sorting)){
				//$this->db->order_by("payment_date","ASC");
			}else{
				//$this->db->order_by($sorting,"ASC");
			}
			
			
			if(!empty($product_group)){
				$this->db->where("c.product_group", $product_group);
			}
			
					
			$this->db->order_by("a.id", 'ASC');
			//$this->db->order_by("c.product_group", 'ASC');
			//$this->db->order_by("c.product_name", 'ASC');
			
			$get_dt = $this->db->get();
			if($get_dt->num_rows() > 0){
				$data_post['report_data'] = $get_dt->result_array();
				
			}
			
			//echo $this->db->last_query();
			//echo '<br/>total item = '.$get_dt->num_rows().'<br/>';
			$all_qty_billing = array();
			$all_qty_item = 0;

			$data_diskon_awal = array();
			$konversi_pembulatan_billing = array();
			$balancing_discount_billing = array();
			$package_billing_product = array();
			$all_product_data = array();
			$newData = array();
			$no = 1;
			if(!empty($data_post['report_data'])){
				foreach ($data_post['report_data'] as $s){
					
					if(empty($all_qty_billing[$s['billing_id']])){
						$all_qty_billing[$s['billing_id']] = array(
							'billing_no'	=> $s['billing_no'],
							'qty_item'		=> 0
						);
					}

					$allow_item = true;
					
					//PACKAGE & PACKAGE ITEM ----------------------------------------------------
					if($s['product_type'] == 'package'){
						//add package
						$package_billing_product[$s['id']] = $s;
					}

					if($s['package_item'] == 1){
						$allow_item = false;

						//ref_order_id
						if(!empty($s['ref_order_id'])){
							if(!empty($package_billing_product[$s['ref_order_id']])){
								if(empty($package_billing_product[$s['ref_order_id']]['package_id'])){
									$package_billing_product[$s['ref_order_id']]['package_id'] = array();
								}
								$package_billing_product[$s['ref_order_id']]['package_id'][] = $s['id'];
							}
						}
					}

					if($allow_item == true){

						$s['item_no'] = $no;
						
						if(empty($all_product_data[$s['product_id']] )){
							
							$all_product_data[$s['product_id']] = array(
								'product_id'	=> $s['product_id'],
								'product_name'	=> $s['product_name'],
								'product_group'	=> $s['product_group'],
								'category_id'	=> $s['category_id'],
								'category_name'	=> $s['category_name'],
								'total_qty'	=> 0,
								'total_billing'	=> 0,
								'total_billing_show'	=> 0,
								'sub_total'	=> 0,
								'sub_total_show'	=> 0,
								'grand_total'	=> 0,
								'grand_total_show'	=> 0,
								'tax_total'	=> 0,
								'tax_total_show'	=> 0,
								'service_total'	=> 0,
								'service_total_show'	=> 0,
								'total_pembulatan'	=> 0,
								'total_pembulatan_show'	=> 0,
								'discount_total'	=> 0,
								'discount_total_show'	=> 0,
								'discount_billing_total'	=> 0,
								'discount_billing_total_show'	=> 0,
								'total_hpp'	=> 0,
								'total_hpp_show'	=> 0,
								'total_profit'	=> 0,
								'total_profit_show'	=> 0,
								'is_compliment'	=> 0,
								'compliment_total'	=> 0
							);
							
							$no++;
							
						}
						
						$all_qty_item += $s['order_qty'];
						$all_qty_billing[$s['billing_id']]['qty_item'] += $s['order_qty'];
						$all_product_data[$s['product_id']]['total_qty'] += $s['order_qty'];
						
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
							$s['tax_total'] = 0;
							$s['service_total'] = 0;
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
						
						
						if(!empty($include_tax) OR !empty($include_service)){
							
							//AUTOFIX-BUGS 1 Jan 2018
							if((!empty($include_tax) AND empty($include_service)) OR (empty($include_tax) AND !empty($include_service))){
								if($s['product_price'] != ($s['product_price_real']+$s['tax_total']+$s['service_total'])){
									$s['product_price_real'] = priceFormat(($s['product_price']/($all_percentage/100)), 0, ".", "");
								}
							}
							
							if($data_post['diskon_sebelum_pajak_service'] == 1){
								
								//$all_product_data[$s['product_id']]['grand_total'] += ($s['product_price_real']*$s['order_qty']) - $s['discount_total'];
								$grand_total_order = ($s['product_price_real']*$s['order_qty'])- $s['discount_total'];
								
							}else{
								
								//$all_product_data[$s['product_id']]['grand_total'] += ($s['product_price_real']*$s['order_qty']);
								$grand_total_order = ($s['product_price_real']*$s['order_qty']);
							
							}
							
							//$all_product_data[$s['product_id']]['total_billing'] += ($s['product_price_real']*$s['order_qty']);
							//$all_product_data[$s['product_id']]['tax_total'] += $s['tax_total'];
							//$all_product_data[$s['product_id']]['service_total'] += $s['service_total'];
							
							$total_billing_order = ($s['product_price_real']*$s['order_qty']);
							$tax_total_order = $s['tax_total'];
							$service_total_order = $s['service_total'];
							
						}else
						{
								
							if($data_post['diskon_sebelum_pajak_service'] == 1){
								
								//$all_product_data[$s['product_id']]['grand_total'] += ($s['product_price']*$s['order_qty'])- $s['discount_total'];
								$grand_total_order = ($s['product_price']*$s['order_qty'])- $s['discount_total'];
								
							}else{
								
								//$all_product_data[$s['product_id']]['grand_total'] += ($s['product_price']*$s['order_qty']);
								$grand_total_order = ($s['product_price']*$s['order_qty']);
							
							}
							
							//$all_product_data[$s['product_id']]['total_billing'] += ($s['product_price']*$s['order_qty']);
							//$all_product_data[$s['product_id']]['tax_total'] += $s['tax_total'];
							//$all_product_data[$s['product_id']]['service_total'] += $s['service_total'];
							
							$total_billing_order = ($s['product_price']*$s['order_qty']);
							$tax_total_order = $s['tax_total'];
							$service_total_order = $s['service_total'];
							
						}


						if(empty($data_diskon_awal[$s['product_id']])){
							$data_diskon_awal[$s['product_id']] = array(
								'item'	=> 0,
								'billing'	=> 0
							);
						}

						//cek if discount is disc billing
						$total_discount_product = 0;
						if($s['discount_perbilling'] == 1){

							$get_percentage = $s['billing_discount_percentage'];
							$sub_total_detail = ($s['product_price']*$s['order_qty']);
							if(empty($s['billing_discount_percentage']) OR $s['billing_discount_percentage'] == '0.00'){
								$get_percentage = ($sub_total_detail / $s['total_billing']) * 100;
								$get_percentage = number_format($get_percentage,2,'.','');
							}
							
							$s['discount_total'] = priceFormat(($s['billing_discount_total']*($get_percentage/100)), 0, ".", "");
							$all_product_data[$s['product_id']]['discount_billing_total'] += $s['discount_total'];
							$total_discount_product = $s['discount_total'];
							//echo '1. total_billing_order = '.$total_billing_order.',get_percentage = '.$get_percentage.',total_discount_product = '.$total_discount_product.'<br/>';
							$data_diskon_awal[$s['product_id']]['billing'] += $total_discount_product;

						}else{
							$all_product_data[$s['product_id']]['discount_total'] += $s['discount_total'];
							$total_discount_product = $s['discount_total'];
							//echo '2. total_discount_product = '.$total_discount_product.'<br/>';
							$data_diskon_awal[$s['product_id']]['item'] += $total_discount_product;
						}
						
						if($s['free_item'] == 1){
							$total_billing_order = ($s['product_price']*$s['order_qty']); 
						}

						//echo '$total_billing_order = '.$total_billing_order.'<br/>';
						//echo '$tax_total_order = '.$tax_total_order.'<br/>';
						//echo '$service_total_order = '.$service_total_order.'<br/>';
						

						$all_product_data[$s['product_id']]['total_hpp'] += ($s['product_price_hpp']*$s['order_qty']);
						$all_product_data[$s['product_id']]['total_billing'] += $total_billing_order;
						$all_product_data[$s['product_id']]['tax_total'] += $tax_total_order;
						$all_product_data[$s['product_id']]['service_total'] += $service_total_order;
						
						//$all_product_data[$s['product_id']]['grand_total'] += $s['tax_total'];
						//$all_product_data[$s['product_id']]['grand_total'] += $s['service_total'];
						
						//BALANCING TOTAL BILLING
						if($s['free_item'] == 1){
							$grand_total_order = $s['discount_total'];
							$total_billing = $grand_total_order;
						}else{
							//$total_billing = $grand_total_order + $s['discount_total'];
							$total_billing = $grand_total_order;
							$grand_total_order += $s['tax_total'];
							$grand_total_order += $s['service_total'];
						}

						//$sub_total = $grand_total_order;
						//$all_product_data[$s['product_id']]['sub_total'] += $grand_total_order;
						
						$all_product_data[$s['product_id']]['grand_total'] += $grand_total_order;
					
						//diskon_sebelum_pajak_service
						if($data_post['diskon_sebelum_pajak_service'] == 0){
							$sub_total = $total_billing + $s['tax_total'] + $s['service_total'];	
							//echo $s['product_id'].', '.$total_billing.' =  +'.$s['tax_total'].' +'.$s['service_total'].', sub_total = '.$sub_total.'<br/>';	

							/*echo 'diskon_sebelum_pajak_service = 0<br/>';
							echo '$total_billing = '.$total_billing.'<br/>';
							echo '$grand_total_order = '.$grand_total_order.'<br/>';
							echo '$sub_total = '.$sub_total.'<br/>';*/

						}else{
							$sub_total = $total_billing - $s['discount_total'] + $s['tax_total'] + $s['service_total'];

							/*echo 'diskon_sebelum_pajak_service = 1<br/>';
							echo '$total_billing = '.$total_billing.'<br/>';
							echo '$grand_total_order = '.$grand_total_order.'<br/>';
							echo '$sub_total = '.$sub_total.'<br/>';*/
						}
						
						$all_product_data[$s['product_id']]['sub_total'] += $sub_total;

						
						//OVERRIDE PEMBULATAN PERITEM
						$total_pembulatan = 0;
						
						$all_product_data[$s['product_id']]['total_pembulatan'] += $total_pembulatan;
						$all_product_data[$s['product_id']]['grand_total'] += $total_pembulatan;
						$grand_total_order += $total_pembulatan;
						
						
						if(!empty($s['is_compliment'])){
							$compliment_total = $grand_total_order;
							$grand_total_order -= $compliment_total;
							$all_product_data[$s['product_id']]['compliment_total'] += $compliment_total;
							$all_product_data[$s['product_id']]['grand_total'] -= $compliment_total;
							$all_product_data[$s['product_id']]['is_compliment'] = 1;
						}
						
						if(!empty($s['payment_id'])){
							if(empty($all_product_data[$s['product_id']]['payment_'.$s['payment_id']])){
								$all_product_data[$s['product_id']]['payment_'.$s['payment_id']] = 0;
							}
							
							$all_product_data[$s['product_id']]['payment_'.$s['payment_id']] += $grand_total_order;
							
						}

						
						/*if($s['product_id'] == '45'){
							echo '<br/>'.$s['id'].', billing_no = '.$s['billing_no'].'<br/>';
							echo '$free_item = '.$s['free_item'].'<br/>';
							echo '$tax_total = '.$s['tax_total'].'<br/>';
							echo '$service_total = '.$s['service_total'].'<br/>';
							echo '$grand_total_order = '.$grand_total_order.'<br/>';
							echo '$discount_total = '.$s['discount_total'].'<br/>';
							echo '$total_billing = '.$total_billing.'<br/>';
						}*/

						
						//BALANCING DISKON
						if(!empty($s['billing_discount_total'])){
							if(empty($balancing_discount_billing[$s['billing_id']])){
								$balancing_discount_billing[$s['billing_id']] = array(
									'billing_no'			=> $s['billing_no'],
									'discount_total'		=> $s['billing_discount_total'],
									'discount_detail_total'	=> 0,
									'payment_id'			=> 0,
									'total_billing'			=> 0,
									'sub_total'				=> 0,
									'discount_perbilling'	=> $s['discount_perbilling'],
									'buyget'				=> 0,
									'free'					=> 0,
									'package'				=> 0,
									'discount_detail'		=> array()
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
									'sub_total_balance'=> 0,
									'discount_balance'=> 0
								);
							}
							$balancing_discount_billing[$s['billing_id']]['discount_detail'][$s['product_id']]['total_discount'] += $total_discount_product;
							$balancing_discount_billing[$s['billing_id']]['discount_detail'][$s['product_id']]['tax_total'] += $s['tax_total'];
							$balancing_discount_billing[$s['billing_id']]['discount_detail'][$s['product_id']]['service_total'] += $s['service_total'];
							$balancing_discount_billing[$s['billing_id']]['discount_detail'][$s['product_id']]['total_billing'] += $total_billing;
							$balancing_discount_billing[$s['billing_id']]['discount_detail'][$s['product_id']]['sub_total'] += $sub_total;
							$balancing_discount_billing[$s['billing_id']]['discount_detail_total'] += $total_discount_product;
							$balancing_discount_billing[$s['billing_id']]['payment_id'] = $s['payment_id'];
							$balancing_discount_billing[$s['billing_id']]['total_billing'] += $total_billing;
							$balancing_discount_billing[$s['billing_id']]['sub_total'] += $sub_total;

							//package
							if($s['package_item'] == 1){
								$balancing_discount_billing[$s['billing_id']]['package'] += 1;
							}
							
							//buyget
							if($s['is_buyget'] == 1){
								$balancing_discount_billing[$s['billing_id']]['buyget'] += 1;
							}

							//free
							if($s['free_item'] == 1){
								$balancing_discount_billing[$s['billing_id']]['free'] += 1;
							}

						}
						
						if(!empty($total_billing)){
							
							//KONVERSI PEMBULATAN PER-ITEM
							if(empty($konversi_pembulatan_billing[$s['billing_id']])){
								$konversi_pembulatan_billing[$s['billing_id']] = array(
									'total_qty'	=> 0,
									'billing_total_pembulatan'	=> $s['billing_total_pembulatan'],
									'total_pembulatan_product'	=> array()
								);
							}
							
							$konversi_pembulatan_billing[$s['billing_id']]['total_qty'] += $s['order_qty'];
							if(empty($konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$s['product_id']])){
								$konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$s['product_id']] = array(
									'total_pembulatan'	=> 0,
									'payment'	=> array()
								);
							}
							
							$konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$s['product_id']]['total_pembulatan'] = $total_pembulatan;
							if(!empty($s['payment_id'])){
								if(empty($konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$s['product_id']]['payment'][$s['payment_id']])){
									$konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$s['product_id']]['payment'][$s['payment_id']] = 0;
								}
								$konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$s['product_id']]['payment'][$s['payment_id']] += $total_pembulatan;
							}
							
						}
					}

				}
			}
			
			//echo '$all_qty_billing = '.count($all_qty_billing).'<br/>';
			//echo '$all_qty_item = '.$all_qty_item.'<br/>';
			//echo 'balancing_discount_billing :'.count($balancing_discount_billing).'<br/>';
			//echo '<pre>';
			//print_r($balancing_discount_billing);
			//die();

			//PEMBAGIAN PEMBULATAN AVERAGE
			$konversi_pembulatan_product = array();
			$konversi_pembulatan_product_payment = array();
			$pembulatan_awal_product = array();
			$pembulatan_awal_product_payment = array();
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
									'total_pembulatan' => 0
								);
							}
							if(empty($pembulatan_awal_product[$product_id])){
								$pembulatan_awal_product[$product_id] = 0;
							}
							
							$pembulatan_awal_product[$product_id] += $data['total_pembulatan'];
							
							$konversi_pembulatan_product[$product_id]['total_pembulatan'] += $pembagian_pembulatan;
							if($no == 1 AND $selisih_pembagian != 0){
								$konversi_pembulatan_product[$product_id]['total_pembulatan'] -= $selisih_pembagian;
							}
							
							//PAYMENT
							if(!empty($data['payment'])){
								foreach($data['payment'] as $payment_id => $dtP){
									if(empty($konversi_pembulatan_product_payment[$product_id][$payment_id])){
										$konversi_pembulatan_product_payment[$product_id][$payment_id] = 0;
									}
									if(empty($pembulatan_awal_product_payment[$product_id][$payment_id])){
										$pembulatan_awal_product_payment[$product_id][$payment_id] = 0;
									}
									$pembulatan_awal_product_payment[$product_id][$payment_id] += $dtP;
									
									$konversi_pembulatan_product_payment[$product_id][$payment_id] += $pembagian_pembulatan;
									if($no == 1 AND $selisih_pembagian != 0){
										$konversi_pembulatan_product_payment[$product_id][$payment_id] -= $selisih_pembagian;
									}
									
								}
								
							}
							//$konversi_data = $data['total_pembulatan'] - $pembagian_pembulatan;
							
							$no++;
						}
					//}
				}
			}
			
			//test reset
			//$konversi_pembulatan_billing = array();
			//$balancing_discount_billing = array();

			//BALANCING DISKON
			//$data_diskon_awal = array();
			$data_diskon_awal_payment = array();
			$data_balancing_diskon = array();
			$data_balancing_diskon_payment = array();
			$data_selisih_diskon = array();
			$data_selisih_diskon_payment = array();
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
					
					//echo '<br/>$billing_id = '.$billing_id.', total_billing = '.$dt['total_billing'].', discount_total = '.$dt['discount_total'].', discount_perbilling='.$dt['discount_perbilling'].', $total_produk = '.$total_produk.'<br/>';

					if(!empty($dt['discount_detail'])){
						
						$no = 0;
						$persentase_total_billing = 0;
						foreach($dt['discount_detail'] as $product_id => $dt_diskon){
							$no++;
							
							//average
							$discount_billing_detail_total = $dt_diskon['total_discount'];
							
							//PERSENTASE DISKON - average by total billing percentage
							$total_disc_prod = 0;
							$persentase_disc_prod = 0;
							if($dt['discount_perbilling'] == 1){
								$total_disc_prod = 0;
								$persentase_disc_prod = ($dt_diskon['total_billing'] / $dt['total_billing']) * 100;
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
							//echo '$discount_billing_detail_total = '.$discount_billing_detail_total.'<br/>';
							//echo '$discount_detail_total = '.$discount_detail_total.'<br/>';

							//echo 'CEK1 -> '.$product_id.' total_discount = '.$dt_diskon['total_discount'].', total_disc_prod = '.$total_disc_prod.',<br/> discount_billing_detail_total = '.$discount_billing_detail_total.'<br/>';
							//echo 'persentase_disc_prod = '.$persentase_disc_prod.', persentase_total_billing = '.$persentase_total_billing.'<br/>';
							
							/*if(empty($data_diskon_awal[$product_id])){
								$data_diskon_awal[$product_id] = array(
									'item'	=> 0,
									'billing'	=> 0
								);
							}*/

							if(empty($data_balancing_diskon[$product_id])){
								$data_balancing_diskon[$product_id] = array(
									'item'	=> 0,
									'billing'	=> 0
								);
							}
							
							
							if(empty($data_balancing_diskon_payment[$product_id])){
								$data_balancing_diskon_payment[$product_id] = array();
							}
							if(empty($data_balancing_diskon_payment[$product_id][$dt['payment_id']])){
								$data_balancing_diskon_payment[$product_id][$dt['payment_id']] = 0;
							}

							if($dt['discount_perbilling'] == 1){
								//$data_diskon_awal[$product_id]['billing'] += $discount_billing_detail_total;
								$data_balancing_diskon[$product_id]['billing'] += $discount_billing_detail_total;
								$data_balancing_diskon_payment[$product_id][$dt['payment_id']] += $discount_billing_detail_total;
							}else{
								//$data_diskon_awal[$product_id]['item'] += $discount_billing_detail_total;
								$data_balancing_diskon[$product_id]['item'] += $discount_billing_detail_total;
								$data_balancing_diskon_payment[$product_id][$dt['payment_id']] += $discount_billing_detail_total;
							}
							
							$balancing_discount_billing[$billing_id]['discount_detail'][$product_id]['total_discount_balance'] = $discount_billing_detail_total;
							
							//echo 'CEK2 -> '.$product_id.' #1 total_billing = '.$dt_diskon['total_billing'].', total_discount_balance = '.$discount_billing_detail_total.' => discount_detail_total = '.$discount_detail_total.'<br/>';

							/*
							//perbilling or package
							if($no == $total_produk AND ($dt['discount_perbilling'] == 1)){

								//$balancing_discount_billing[$billing_id]['discount_detail_total'] = $discount_detail_total;

								if($discount_detail_total != $dt['discount_total']){
								//if($dt['discount_detail_total'] != $dt['discount_total']){
									$discount_detail_total = priceFormat($discount_detail_total, 2, ".", "");	
									$selisih_akhir =  $dt['discount_total'] - $discount_detail_total;
									//$selisih_akhir =  $dt['discount_total'] - $dt['discount_detail_total'];
									
									//echo 'CEK4 -> '.$product_id.', discount_total = '.$dt['discount_total'].', - discount_detail_total = '.$discount_detail_total.' => discount_billing_detail_total '.$discount_billing_detail_total.', selisih_akhir = '.$selisih_akhir.', data_balancing_diskon_billing => '.$data_balancing_diskon[$product_id]['billing'].', total_discount_balance = '.$balancing_discount_billing[$billing_id]['discount_detail'][$product_id]['total_discount_balance'].'<br/>';

									if($dt['discount_perbilling'] == 1){
										$data_balancing_diskon[$product_id]['billing'] += $selisih_akhir;
									}else{
										$data_balancing_diskon[$product_id]['item'] += $selisih_akhir;
									}
									
									$balancing_discount_billing[$billing_id]['discount_detail'][$product_id]['total_discount_balance'] -= $selisih_akhir;

									//echo 'CEK5 -> '.$product_id.', total_billing = '.$dt_diskon['total_billing'].', selisih_akhir = '.$selisih_akhir.'<br/><br/>';
									
								}
							}
							*/

							//echo '<br/>';

						}
						
					}
				}
				
				//SET SELISIH DISKON
				if(!empty($balancing_discount_billing)){
					foreach($balancing_discount_billing as $billing_id => $dt){
						if(!empty($dt['discount_detail'])){
							//echo 'SSD #'.$billing_id.', discount_perbilling = '.$dt['discount_perbilling'].'<br/>';
							//echo '<pre>';
							//print_r($dt);
							$discount_detail_total = 0;
							foreach($dt['discount_detail'] as $product_id => $dt_diskon){
								
								//$sub_total_balance = $dt_diskon['total_billing'] - $dt_diskon['total_discount'];
								$sub_total_balance = $dt_diskon['total_billing'];
								//echo '$sub_total_balance = '.$sub_total_balance.'<br/>';

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
								
								//echo 'sub_total_balance = '.$sub_total_balance.' <> sub_total = '.$dt_diskon['sub_total'].', sub_total_selisih = '.$sub_total_selisih.' <br/>';

								if(empty($data_selisih_diskon[$product_id])){
									$data_selisih_diskon[$product_id] = 0;
								}
								
								$data_selisih_diskon[$product_id] += $sub_total_selisih;
								
								if(empty($data_selisih_diskon_payment[$product_id])){
									$data_selisih_diskon_payment[$product_id] = array();;
								}
								
								if(empty($data_selisih_diskon_payment[$product_id][$dt['payment_id']])){
									$data_selisih_diskon_payment[$product_id][$dt['payment_id']] = 0;
								}
								
								//echo $product_id.' -> '.$dt['payment_id'].' <br/>';
								$data_selisih_diskon_payment[$product_id][$dt['payment_id']] += $sub_total_selisih;
								
							}
							//echo '<br/>';
						}
					}
				}
			}
			
			
			//echo '<pre>';
			//echo '$data_diskon_awal: <br/>';
			//print_r($data_diskon_awal);
			//echo '$data_balancing_diskon: <br/>';
			//print_r($data_balancing_diskon);
			//echo '$data_balancing_diskon_payment: <br/>';
			//print_r($data_balancing_diskon_payment);
			//echo '$data_selisih_diskon: <br/>';
			//print_r($data_selisih_diskon);
			//echo '$data_selisih_diskon_payment: <br/>';
			//print_r($data_selisih_diskon_payment);
			//echo '$balancing_discount_billing: <br/>';
			//print_r($balancing_discount_billing);
			//echo 'TOTAL = '.count($all_product_data);
			//die();

			//$data_selisih_diskon = array();
			//$data_selisih_diskon_payment = array();
			
			$sort_qty = array();
			$sort_profit = array();
			$no = 1;
			if(!empty($all_product_data)){
				foreach($all_product_data as $dt){
					$dt['item_no'] = $no;
					
					$sort_qty[$dt['product_id']] = $dt['total_qty'];
					
					//BALANCING DISKON
					if(!empty($data_diskon_awal[$dt['product_id']])){
						$dt['discount_total'] -= $data_diskon_awal[$dt['product_id']]['item'];
						$dt['discount_billing_total'] -= $data_diskon_awal[$dt['product_id']]['billing'];
					}
					
					if(!empty($data_balancing_diskon[$dt['product_id']])){
						$dt['discount_total'] += $data_balancing_diskon[$dt['product_id']]['item'];
						$dt['discount_billing_total'] += $data_balancing_diskon[$dt['product_id']]['billing'];
					}

					//echo 'sub_total='.$dt['sub_total'].'<br/>';
					//echo 'discount_total='.$dt['discount_total'].'<br/>';
					//echo 'discount_billing_total='.$dt['discount_billing_total'].'<br/>';
					
					$dt['grand_total'] -=$dt['discount_total'];
					$dt['grand_total'] -=$dt['discount_billing_total'];

					//echo 'grandtotal='.$dt['grand_total'].'<br/>';

					
					if(!empty($data_selisih_diskon[$dt['product_id']])){
						//$dt['sub_total'] -= $data_selisih_diskon[$dt['product_id']];
						$dt['grand_total'] -= $data_selisih_diskon[$dt['product_id']];
					}
					
					//BALANCING DISKON PAYMENT
					if(!empty($data_balancing_diskon_payment[$dt['product_id']])){
						foreach($data_balancing_diskon_payment[$dt['product_id']] as $payment_id => $dtP){
							if(!empty($dt['payment_'.$payment_id])){
								$dt['payment_'.$payment_id] -= $dtP;
							}
						}
					}

					if(!empty($data_selisih_diskon_payment[$dt['product_id']])){
						foreach($data_selisih_diskon_payment[$dt['product_id']] as $payment_id => $dtP){
							if(!empty($dt['payment_'.$payment_id])){
								$dt['payment_'.$payment_id] -= $dtP;
							}
						}
					}
					
					
					//KONVERSI PEMBULATAN
					$selisih_pembulatan = 0;
					if(!empty($pembulatan_awal_product[$dt['product_id']])){
						$selisih_pembulatan -= $pembulatan_awal_product[$dt['product_id']];
						$dt['grand_total'] -= $pembulatan_awal_product[$dt['product_id']];
					}
					
					
					if(!empty($konversi_pembulatan_product[$dt['product_id']])){
						$dt['total_pembulatan'] = $konversi_pembulatan_product[$dt['product_id']]['total_pembulatan'];
						$dt['grand_total'] += $konversi_pembulatan_product[$dt['product_id']]['total_pembulatan'];
						$selisih_pembulatan += $konversi_pembulatan_product[$dt['product_id']]['total_pembulatan'];
					}
					
					if(!empty($dt['compliment_total'])){
						$dt['compliment_total'] += $selisih_pembulatan;
					}
					
					//KONVERSI PEMBULATAN PAYMENT
					if(!empty($pembulatan_awal_product_payment[$dt['product_id']])){
						foreach($pembulatan_awal_product_payment[$dt['product_id']] as $payment_id => $dtP){
							if(!empty($dt['payment_'.$payment_id])){
								$dt['payment_'.$payment_id] -= $dtP;
							}
						}
					}
					
					if(!empty($konversi_pembulatan_product_payment[$dt['product_id']])){
						foreach($konversi_pembulatan_product_payment[$dt['product_id']] as $payment_id => $dtP){
							if(!empty($dt['payment_'.$payment_id])){
								$dt['payment_'.$payment_id] += $dtP;
							}
						}
					}
					
					
					$dt['total_billing_show'] = priceFormat($dt['total_billing']);
					$dt['grand_total_show'] = priceFormat($dt['grand_total']);
					$dt['sub_total_show'] = priceFormat($dt['sub_total']);
					$dt['tax_total_show'] = priceFormat($dt['tax_total']);
					$dt['service_total_show'] = priceFormat($dt['service_total']);
					
					$dt['total_pembulatan_show'] = priceFormat($dt['total_pembulatan']);
					$dt['discount_total_show'] = priceFormat($dt['discount_total']);
					$dt['discount_billing_total_show'] = priceFormat($dt['discount_billing_total']);
					$dt['compliment_total_show'] = priceFormat($dt['compliment_total']);
					
					$dt['total_profit'] = $dt['total_billing']-$dt['total_hpp'];
					$dt['total_hpp_show'] = priceFormat($dt['total_hpp']);
					$dt['total_profit_show'] = priceFormat($dt['total_profit']);
					$sort_profit[$dt['product_id']] = $dt['total_profit'];
										
					$newData[$dt['product_id']] = $dt;
					$no++;
				}
			}
		
			arsort($sort_qty);	
			if(!empty($order_qty)){
				//RANK QTY
				if($order_qty == 1){
					arsort($sort_qty);
					$xnewData = array();
					foreach($sort_qty as $key => $dt){
			
						if(!empty($newData[$key])){
							$xnewData[] = $newData[$key];
						}
							
					}
					$newData = $xnewData;
				}
					
				//RANK PROFIT
				if($order_qty == 2){
					arsort($sort_profit);
					$xnewData = array();
					foreach($sort_profit as $key => $dt){
			
						if(!empty($newData[$key])){
							$xnewData[] = $newData[$key];
						}
							
					}
					$newData = $xnewData;
				}
			}else{
				$order_qty = 0;
				$xnewData = array();
				foreach($newData as $dt){
					$xnewData[] = $dt;
				}
					
				$newData = $xnewData;
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
			


			//GROUPING FOOD BEVERAGE
			$new_GroupData = array();
			foreach($newData as $dt){
				
				$ProdGroup = strtoupper($dt['product_group']);
				
				if(empty($new_GroupData[$ProdGroup])){
					$new_GroupData[$ProdGroup] = array();
				}
					
				$new_GroupData[$ProdGroup][] = $dt;
			}
			
			krsort($new_GroupData);
			$newData = $new_GroupData;
			
			$data_post['report_data'] = $newData;
			$data_post['payment_data'] = $dt_payment_name;
						
		}
				
		//DO-PRINT
		if(!empty($do)){
			$data_post['do'] = $do;
		}else{
			$do = '';
		}

		if(empty($useview)){
			$useview = 'print_reportSalesByFoodBeverage';
			$data_post['report_name'] = 'SALES REPORT BY FOOD AND BEVERAGE';
			
			if($do == 'excel'){
				$useview = 'excel_reportSalesByFoodBeverage';
			}
		
		}else{
			$useview = 'print_reportProfitSalesByFoodBeverage';
			$data_post['report_name'] = 'SALES PROFIT REPORT FOOD AND BEVERAGE';
			
			if($do == 'excel'){
				$useview = 'excel_reportProfitSalesByFoodBeverage';
			}
			
		}
		
		$this->load->view('../../billing/views/'.$useview, $data_post);
	}
	
	public function print_reportSalesByFoodBeverageRecap(){
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
		
		$data_post = array(
			'do'	=> '',
			'report_data'	=> array(),
			'report_place_default'	=> '',
			'report_name'	=> 'SALES REPORT BY FOOD AND BEVERAGE (RECAP)',
			'date_from'	=> $date_from,
			'date_till'	=> $date_till,
			'cashier_name'	=> '',
			'user_fullname'	=> $user_fullname,
			'diskon_sebelum_pajak_service'	=> 0
		);
		
		if(empty($groupCat)){
			$groupCat = 0;
		}
		
		$get_opt = get_option_value(array('report_place_default','diskon_sebelum_pajak_service','cashier_max_pembulatan',
		'cashier_pembulatan_keatas','pembulatan_dinamis'));
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
						
			$qdate_from = date("Y-m-d",strtotime($date_from));
			$qdate_till = date("Y-m-d",strtotime($date_till));
			$qdate_till_max = date("Y-m-d",strtotime($date_till)+ONE_DAY_UNIX);
			
			$add_where = "(b.payment_date >= '".$qdate_from." 07:00:00' AND b.payment_date <= '".$qdate_till_max." 06:00:00')";
			
			/*
			b.tax_total, b.service_total,
			b.include_tax, b.tax_percentage, b.include_service, b.service_percentage, b.discount_total,
			*/

			$this->db->select("a.*, b.payment_date, b.billing_no, b.total_billing, b.discount_perbilling, b.payment_id, 
								b.discount_percentage as billing_discount_percentage, b.discount_total as billing_discount_total,
								b.total_pembulatan as billing_total_pembulatan,
								c.product_name, c.product_group, c.category_id, d.product_category_name as category_name");
			$this->db->from($this->table2." as a");
			$this->db->join($this->prefix.'billing as b','b.id = a.billing_id','LEFT');
			$this->db->join($this->prefix.'product as c','c.id = a.product_id','LEFT');
			$this->db->join($this->prefix.'product_category as d','d.id = c.category_id','LEFT');
			$this->db->where("(a.order_status != 'cancel' AND a.order_qty > 0)");	
			$this->db->where("a.is_deleted", 0);
			$this->db->where("b.is_deleted", 0);
			$this->db->where("b.billing_status", "paid");			
			$this->db->order_by("c.product_name", 'ASC');
			$this->db->where($add_where);
			
			if(empty($sorting)){
				//$this->db->order_by("b.payment_date","ASC");
			}else{
				//$this->db->order_by($sorting,"ASC");
			}
			
			
			if(!empty($product_group)){
				$this->db->where("c.product_group", $product_group);
			}
			
					
			$this->db->order_by("a.id", 'ASC');
			//$this->db->order_by("c.product_group", 'ASC');
			//$this->db->order_by("c.product_name", 'ASC');
			
			$get_dt = $this->db->get();
			if($get_dt->num_rows() > 0){
				$data_post['report_data'] = $get_dt->result_array();
				
			}
			
			//echo $this->db->last_query();
			//echo '<br/>total item = '.$get_dt->num_rows().'<br/>';
			$all_qty_billing = array();
			$all_qty_item = 0;
			
			$all_bil_id = array();
			$data_diskon_awal = array();
			$konversi_pembulatan_billing = array();
			$balancing_discount_billing = array();
			$package_billing_product = array();
			$all_group_date = array();
			$newData = array();
			$no_id = 1;
			if(!empty($data_post['report_data'])){
				foreach ($data_post['report_data'] as $s){
					if(empty($all_qty_billing[$s['billing_id']])){
						$all_qty_billing[$s['billing_id']] = array(
							'billing_no'	=> $s['billing_no'],
							'qty_item'		=> 0
						);
					}

					$allow_item = true;
					
					//PACKAGE & PACKAGE ITEM ----------------------------------------------------
					if($s['product_type'] == 'package'){
						//add package
						$package_billing_product[$s['id']] = $s;
					}

					if($s['package_item'] == 1){
						$allow_item = false;

						//ref_order_id
						if(!empty($s['ref_order_id'])){
							if(!empty($package_billing_product[$s['ref_order_id']])){
								if(empty($package_billing_product[$s['ref_order_id']]['package_id'])){
									$package_billing_product[$s['ref_order_id']]['package_id'] = array();
								}
								$package_billing_product[$s['ref_order_id']]['package_id'][] = $s['id'];
							}
						}
					}

					if($allow_item == true){
					
						//REKAP TGL
						$payment_date = date("d-m-Y",strtotime($s['payment_date']));
						if(empty($all_group_date[$payment_date])){
							$all_group_date[$payment_date] = array(
								'id'		=> $no_id, 
								'item_no'	=> $no_id, 
								'date'		=> $payment_date, 
								'qty_billing'		=> 0, 
								'total_food'		=> 0, 
								'total_food_show'	=> 0, 
								'total_beverage'	=> 0, 
								'total_beverage_show'	=> 0, 
								'total_other'	=> 0, 
								'total_other_show'	=> 0, 
								'total_billing'		=> 0, 
								'total_billing_show'=> 0,
								'sub_total'		=> 0, 
								'sub_total_show'=> 0,
								'tax_total'			=> 0, 
								'tax_total_show'	=> 0, 
								'service_total'		=> 0, 
								'service_total_show'=> 0,
								'total_pembulatan'		=> 0, 
								'total_pembulatan_show'=> 0,
								'discount_total'	=> 0, 
								'discount_total_show'=> 0, 
								'discount_billing_total'	=> 0, 
								'discount_billing_show'=> 0, 
								'total_dp'			=> 0, 
								'total_dp_show'		=> 0, 
								'grand_total'		=> 0, 
								'grand_total_show'	=> 0, 
								'total_compliment'		=> 0, 
								'total_compliment_show'	=> 0,
								'total_hpp'			=> 0, 
								'total_hpp_show'	=> 0, 
								'total_profit'		=> 0, 
								'total_profit_show'=> 0, 
								'is_compliment'=> 0, 
								'compliment_total'=> 0
							);
						}

						$no_id++;
						
						if(!in_array($s['billing_id'], $all_bil_id)){
							$all_bil_id[] = $s['billing_id'];
							
							//$all_group_date[$payment_date]['discount_total'] += $s['discount_total'];
							//$all_group_date[$payment_date]['total_dp'] += $s['total_dp'];
							
							if(!empty($s['is_compliment'])){
								//$all_group_date[$payment_date]['total_compliment'] += ($s['total_billing'] + $s['tax_total'] + $s['service_total']);
							}
							
						}
						
						$all_qty_item += $s['order_qty'];
						$all_qty_billing[$s['billing_id']]['qty_item'] += $s['order_qty'];
						$all_group_date[$payment_date]['qty_billing'] += $s['order_qty'];
						
						
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
							$s['tax_total'] = 0;
							$s['service_total'] = 0;
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

						
						if(!empty($include_tax) OR !empty($include_service)){
							
							//AUTOFIX-BUGS 1 Jan 2018
							if((!empty($include_tax) AND empty($include_service)) OR (empty($include_tax) AND !empty($include_service))){
								if($s['product_price'] != ($s['product_price_real']+$s['tax_total']+$s['service_total'])){
									$s['product_price_real'] = priceFormat(($s['product_price']/($all_percentage/100)), 0, ".", "");
								}
							}
							
							if($data_post['diskon_sebelum_pajak_service'] == 1){
								
								//$all_group_date[$payment_date]['grand_total'] += ($s['product_price_real']*$s['order_qty']) - $s['discount_total'];
								$grand_total_order = ($s['product_price_real']*$s['order_qty']) - $s['discount_total'];
							
							}else{
								
								//$all_group_date[$payment_date]['grand_total'] += ($s['product_price_real']*$s['order_qty']);
								$grand_total_order = ($s['product_price_real']*$s['order_qty']);
							
							}
							
							//$all_group_date[$payment_date]['total_billing'] += ($s['product_price_real']*$s['order_qty']);
							//$all_group_date[$payment_date]['tax_total'] += $s['tax_total'];
							//$all_group_date[$payment_date]['service_total'] += $s['service_total'];
							
							$total_billing_order = ($s['product_price_real']*$s['order_qty']);
							$tax_total_order = $s['tax_total'];
							$service_total_order = $s['service_total'];
							
							if($s['product_group'] == 'food'){
								$all_group_date[$payment_date]['total_food'] += ($s['product_price_real']*$s['order_qty']);
							}else
							if($s['product_group'] == 'beverage'){
								$all_group_date[$payment_date]['total_beverage'] += ($s['product_price_real']*$s['order_qty']);
							}else{
								$all_group_date[$payment_date]['total_other'] += ($s['product_price_real']*$s['order_qty']);
							}
							
						}else
						{
							
							if($data_post['diskon_sebelum_pajak_service'] == 1){
								
								//$all_group_date[$payment_date]['grand_total'] += ($s['product_price']*$s['order_qty']) - $s['discount_total'];
								$grand_total_order = ($s['product_price']*$s['order_qty']) - $s['discount_total'];
								
							}else{
								
								//$all_group_date[$payment_date]['grand_total'] += ($s['product_price']*$s['order_qty']);
								$grand_total_order = ($s['product_price']*$s['order_qty']);
							
							}
							
							//$all_group_date[$payment_date]['total_billing'] += ($s['product_price']*$s['order_qty']);
							//$all_group_date[$payment_date]['tax_total'] += $s['tax_total'];
							//$all_group_date[$payment_date]['service_total'] += $s['service_total'];
							
							$total_billing_order = ($s['product_price']*$s['order_qty']);
							$tax_total_order = $s['tax_total'];
							$service_total_order = $s['service_total'];
							
							if($s['product_group'] == 'food'){
								$all_group_date[$payment_date]['total_food'] += ($s['product_price']*$s['order_qty']);
							}else
							if($s['product_group'] == 'beverage'){
								$all_group_date[$payment_date]['total_beverage'] += ($s['product_price']*$s['order_qty']);
							}else{
								$all_group_date[$payment_date]['total_other'] += ($s['product_price']*$s['order_qty']);
							}
						}


						if(empty($data_diskon_awal[$payment_date])){
							$data_diskon_awal[$payment_date] = array(
								'item'	=> 0,
								'billing'	=> 0
							);
						}

						//cek if discount is disc billing
						$total_discount_product = 0;
						if($s['discount_perbilling'] == 1){

							$get_percentage = $s['billing_discount_percentage'];
							$sub_total_detail = ($s['product_price']*$s['order_qty']);
							if(empty($s['billing_discount_percentage']) OR $s['billing_discount_percentage'] == '0.00'){
								$get_percentage = ($sub_total_detail / $s['total_billing']) * 100;
								$get_percentage = number_format($get_percentage,2,'.','');
							}
							
							$s['discount_total'] = priceFormat(($s['billing_discount_total']*($get_percentage/100)), 0, ".", "");
							$all_group_date[$payment_date]['discount_billing_total'] += $s['discount_total'];
							$total_discount_product = $s['discount_total'];
							//echo '1. total_billing_order = '.$total_billing_order.',get_percentage = '.$get_percentage.',total_discount_product = '.$total_discount_product.'<br/>';
							$data_diskon_awal[$payment_date]['billing'] += $total_discount_product;

						}else{
							$all_group_date[$payment_date]['discount_total'] += $s['discount_total'];
							$total_discount_product = $s['discount_total'];
							//echo '2. total_discount_product = '.$total_discount_product.'<br/>';
							$data_diskon_awal[$payment_date]['item'] += $total_discount_product;
						}
						
						if($s['free_item'] == 1){
							$total_billing_order = ($s['product_price']*$s['order_qty']); 
						}

						//echo '$total_billing_order = '.$total_billing_order.'<br/>';
						//echo '$tax_total_order = '.$tax_total_order.'<br/>';
						//echo '$service_total_order = '.$service_total_order.'<br/>';
							
						$all_group_date[$payment_date]['total_hpp'] += ($s['product_price_hpp']*$s['order_qty']);
						$all_group_date[$payment_date]['total_billing'] += $total_billing_order;
						$all_group_date[$payment_date]['tax_total'] += $tax_total_order;
						$all_group_date[$payment_date]['service_total'] += $service_total_order;
						
						//$all_group_date[$payment_date]['grand_total'] += $s['tax_total'];
						//$all_group_date[$payment_date]['grand_total'] += $s['service_total'];
						
						//BALANCING TOTAL BILLING
						if($s['free_item'] == 1){
							$grand_total_order = $s['discount_total'];
							$total_billing = $grand_total_order;
						}else{
							//$total_billing = $grand_total_order + $s['discount_total'];
							$total_billing = $grand_total_order;
							$grand_total_order += $s['tax_total'];
							$grand_total_order += $s['service_total'];
						}
						
						//$sub_total = $grand_total_order;
						//$all_group_date[$payment_date]['sub_total'] += $grand_total_order;
						
						$all_group_date[$payment_date]['grand_total'] += $grand_total_order;
					
						//diskon_sebelum_pajak_service
						if($data_post['diskon_sebelum_pajak_service'] == 0){
							$sub_total = $total_billing + $s['tax_total'] + $s['service_total'];	
							//echo $payment_date.', '.$total_billing.' =  +'.$s['tax_total'].' +'.$s['service_total'].', sub_total = '.$sub_total.'<br/>';	

							/*echo 'diskon_sebelum_pajak_service = 0<br/>';
							echo '$total_billing = '.$total_billing.'<br/>';
							echo '$grand_total_order = '.$grand_total_order.'<br/>';
							echo '$sub_total = '.$sub_total.'<br/>';*/

						}else{
							$sub_total = $total_billing - $s['discount_total'] + $s['tax_total'] + $s['service_total'];

							/*echo 'diskon_sebelum_pajak_service = 1<br/>';
							echo '$total_billing = '.$total_billing.'<br/>';
							echo '$grand_total_order = '.$grand_total_order.'<br/>';
							echo '$sub_total = '.$sub_total.'<br/>';*/
						}
						
						$all_group_date[$payment_date]['sub_total'] += $sub_total;
						
						
						//OVERRIDE PEMBULATAN PERITEM
						$total_pembulatan = 0;
						
						$all_group_date[$payment_date]['total_pembulatan'] += $total_pembulatan;
						$all_group_date[$payment_date]['grand_total'] += $total_pembulatan;
						$grand_total_order += $total_pembulatan;
						
						$grand_total_order += $total_pembulatan;
						
						
						if(!empty($s['is_compliment'])){
							$compliment_total = $grand_total_order;
							$grand_total_order -= $compliment_total;
							$all_group_date[$payment_date]['compliment_total'] += $compliment_total;
							$all_group_date[$payment_date]['grand_total'] -= $compliment_total;
							$all_group_date[$payment_date]['is_compliment'] = 1;
						}
						
						//$all_group_date[$payment_date]['discount_total'] += $s['discount_total'];
						$all_group_date[$payment_date]['total_hpp'] += ($s['product_price_hpp']*$s['order_qty']);
						
						
						if(!empty($s['payment_id'])){
							if(empty($all_group_date[$payment_date]['payment_'.$s['payment_id']])){
								$all_group_date[$payment_date]['payment_'.$s['payment_id']] = 0;
							}
							
							$all_group_date[$payment_date]['payment_'.$s['payment_id']] += $grand_total_order;
							
						}

						
						/*if($payment_date == '45'){
							echo '<br/>'.$s['id'].', billing_no = '.$s['billing_no'].'<br/>';
							echo '$free_item = '.$s['free_item'].'<br/>';
							echo '$tax_total = '.$s['tax_total'].'<br/>';
							echo '$service_total = '.$s['service_total'].'<br/>';
							echo '$grand_total_order = '.$grand_total_order.'<br/>';
							echo '$discount_total = '.$s['discount_total'].'<br/>';
							echo '$total_billing = '.$total_billing.'<br/>';
						}*/

						
						//BALANCING DISKON
						if(!empty($s['billing_discount_total'])){
							if(empty($balancing_discount_billing[$s['billing_id']])){
								$balancing_discount_billing[$s['billing_id']] = array(
									'billing_no'			=> $s['billing_no'],
									'discount_total'		=> $s['billing_discount_total'],
									'discount_detail_total'	=> 0,
									'payment_id'			=> 0,
									'total_billing'			=> 0,
									'sub_total'				=> 0,
									'discount_perbilling'	=> $s['discount_perbilling'],
									'buyget'				=> 0,
									'free'					=> 0,
									'package'				=> 0,
									'discount_detail'		=> array()
								);
							}
						}
						
						if(!empty($s['billing_discount_total'])){
							if(empty($balancing_discount_billing[$s['billing_id']]['discount_detail'][$payment_date])){
								$balancing_discount_billing[$s['billing_id']]['discount_detail'][$payment_date] = array(
									'total_discount'=> 0,
									'total_discount_balance'=> 0,
									'tax_total'	=> 0,
									'service_total'	=> 0,
									'total_billing'	=> 0,
									'sub_total'	=> 0,
									'sub_total_balance'=> 0,
									'discount_balance'=> 0
								);
							}
							$balancing_discount_billing[$s['billing_id']]['discount_detail'][$payment_date]['total_discount'] += $total_discount_product;
							$balancing_discount_billing[$s['billing_id']]['discount_detail'][$payment_date]['tax_total'] += $s['tax_total'];
							$balancing_discount_billing[$s['billing_id']]['discount_detail'][$payment_date]['service_total'] += $s['service_total'];
							$balancing_discount_billing[$s['billing_id']]['discount_detail'][$payment_date]['total_billing'] += $total_billing;
							$balancing_discount_billing[$s['billing_id']]['discount_detail'][$payment_date]['sub_total'] += $sub_total;
							$balancing_discount_billing[$s['billing_id']]['discount_detail_total'] += $total_discount_product;
							$balancing_discount_billing[$s['billing_id']]['payment_id'] = $s['payment_id'];
							$balancing_discount_billing[$s['billing_id']]['total_billing'] += $total_billing;
							$balancing_discount_billing[$s['billing_id']]['sub_total'] += $sub_total;

							//package
							if($s['package_item'] == 1){
								$balancing_discount_billing[$s['billing_id']]['package'] += 1;
							}
							
							//buyget
							if($s['is_buyget'] == 1){
								$balancing_discount_billing[$s['billing_id']]['buyget'] += 1;
							}

							//free
							if($s['free_item'] == 1){
								$balancing_discount_billing[$s['billing_id']]['free'] += 1;
							}
						}
						
						if(!empty($total_billing)){

							//KONVERSI PEMBULATAN PER-ITEM
							if(empty($konversi_pembulatan_billing[$s['billing_id']])){
								$konversi_pembulatan_billing[$s['billing_id']] = array(
									'total_qty'	=> 0,
									'billing_total_pembulatan'	=> $s['billing_total_pembulatan'],
									'total_pembulatan_product'	=> array()
								);
							}
							
							$konversi_pembulatan_billing[$s['billing_id']]['total_qty'] += $s['order_qty'];
							if(empty($konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$payment_date])){
								$konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$payment_date] = array(
									'total_pembulatan'	=> 0,
									'payment'	=> array()
								);
							}
							$konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$payment_date]['total_pembulatan'] = $total_pembulatan;
							if(!empty($s['payment_id'])){
								if(empty($konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$payment_date]['payment'][$s['payment_id']])){
									$konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$payment_date]['payment'][$s['payment_id']] = 0;
								}
								$konversi_pembulatan_billing[$s['billing_id']]['total_pembulatan_product'][$payment_date]['payment'][$s['payment_id']] += $total_pembulatan;
							}
						}
						
					}
				}
			}
			
			//echo '$all_qty_billing = '.count($all_qty_billing).'<br/>';
			//echo '$all_qty_item = '.$all_qty_item.'<br/>';
			//echo 'balancing_discount_billing :'.count($balancing_discount_billing).'<br/>';
			//echo '<pre>';
			//print_r($balancing_discount_billing);
			//die();
			
			//PEMBAGIAN PEMBULATAN AVERAGE
			$konversi_pembulatan_product = array();
			$konversi_pembulatan_product_payment = array();
			$pembulatan_awal_product = array();
			$pembulatan_awal_product_payment = array();
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
									'total_pembulatan' => 0
								);
							}
							if(empty($pembulatan_awal_product[$product_id])){
								$pembulatan_awal_product[$product_id] = 0;
							}
							
							$pembulatan_awal_product[$product_id] += $data['total_pembulatan'];
							
							$konversi_pembulatan_product[$product_id]['total_pembulatan'] += $pembagian_pembulatan;
							if($no == 1 AND $selisih_pembagian != 0){
								$konversi_pembulatan_product[$product_id]['total_pembulatan'] -= $selisih_pembagian;
							}
							
							//PAYMENT
							if(!empty($data['payment'])){
								foreach($data['payment'] as $payment_id => $dtP){
									if(empty($konversi_pembulatan_product_payment[$product_id][$payment_id])){
										$konversi_pembulatan_product_payment[$product_id][$payment_id] = 0;
									}
									if(empty($pembulatan_awal_product_payment[$product_id][$payment_id])){
										$pembulatan_awal_product_payment[$product_id][$payment_id] = 0;
									}
									$pembulatan_awal_product_payment[$product_id][$payment_id] += $dtP;
									
									$konversi_pembulatan_product_payment[$product_id][$payment_id] += $pembagian_pembulatan;
									if($no == 1 AND $selisih_pembagian != 0){
										$konversi_pembulatan_product_payment[$product_id][$payment_id] -= $selisih_pembagian;
									}
									
								}
								
							}
							//$konversi_data = $data['total_pembulatan'] - $pembagian_pembulatan;
							
							$no++;
						}
					//}
				}
			}
			
			//test reset
			//$konversi_pembulatan_billing = array();
			//$balancing_discount_billing = array();
			
			//BALANCING DISKON
			//$data_diskon_awal = array();
			$data_diskon_awal_payment = array();
			$data_balancing_diskon = array();
			$data_balancing_diskon_payment = array();
			$data_selisih_diskon = array();
			$data_selisih_diskon_payment = array();
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
					
					//echo '<br/>$billing_id = '.$billing_id.', total_billing = '.$dt['total_billing'].', discount_total = '.$dt['discount_total'].', discount_perbilling='.$dt['discount_perbilling'].', $total_produk = '.$total_produk.'<br/>';
					
					if(!empty($dt['discount_detail'])){
						
						$no = 0;
						$persentase_total_billing = 0;
						foreach($dt['discount_detail'] as $product_id => $dt_diskon){
							$no++;
							
							//average
							$discount_billing_detail_total = $dt_diskon['total_discount'];
							
							//PERSENTASE DISKON - average by total billing percentage
							$total_disc_prod = 0;
							$persentase_disc_prod = 0;
							if($dt['discount_perbilling'] == 1){
								$total_disc_prod = 0;
								$persentase_disc_prod = ($dt_diskon['total_billing'] / $dt['total_billing']) * 100;
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
							//echo '$discount_billing_detail_total = '.$discount_billing_detail_total.'<br/>';
							//echo '$discount_detail_total = '.$discount_detail_total.'<br/>';

							//echo 'CEK1 -> '.$product_id.' total_discount = '.$dt_diskon['total_discount'].', total_disc_prod = '.$total_disc_prod.',<br/> discount_billing_detail_total = '.$discount_billing_detail_total.'<br/>';
							//echo 'persentase_disc_prod = '.$persentase_disc_prod.', persentase_total_billing = '.$persentase_total_billing.'<br/>';
							
							/*if(empty($data_diskon_awal[$product_id])){
								$data_diskon_awal[$product_id] = array(
									'item'	=> 0,
									'billing'	=> 0
								);
							}*/

							if(empty($data_balancing_diskon[$product_id])){
								$data_balancing_diskon[$product_id] = array(
									'item'	=> 0,
									'billing'	=> 0
								);
							}
							
							
							if(empty($data_balancing_diskon_payment[$product_id])){
								$data_balancing_diskon_payment[$product_id] = array();
							}
							if(empty($data_balancing_diskon_payment[$product_id][$dt['payment_id']])){
								$data_balancing_diskon_payment[$product_id][$dt['payment_id']] = 0;
							}

							if($dt['discount_perbilling'] == 1){
								//$data_diskon_awal[$product_id]['billing'] += $discount_billing_detail_total;
								$data_balancing_diskon[$product_id]['billing'] += $discount_billing_detail_total;
								$data_balancing_diskon_payment[$product_id][$dt['payment_id']] += $discount_billing_detail_total;
							}else{
								//$data_diskon_awal[$product_id]['item'] += $discount_billing_detail_total;
								$data_balancing_diskon[$product_id]['item'] += $discount_billing_detail_total;
								$data_balancing_diskon_payment[$product_id][$dt['payment_id']] += $discount_billing_detail_total;
							}
							
							$balancing_discount_billing[$billing_id]['discount_detail'][$product_id]['total_discount_balance'] = $discount_billing_detail_total;
							
							//echo 'CEK2 -> '.$product_id.' #1 total_billing = '.$dt_diskon['total_billing'].', total_discount_balance = '.$discount_billing_detail_total.' => discount_detail_total = '.$discount_detail_total.'<br/>';

							/*
							//perbilling or package
							if($no == $total_produk AND ($dt['discount_perbilling'] == 1)){

								//$balancing_discount_billing[$billing_id]['discount_detail_total'] = $discount_detail_total;

								if($discount_detail_total != $dt['discount_total']){
								//if($dt['discount_detail_total'] != $dt['discount_total']){
									$discount_detail_total = priceFormat($discount_detail_total, 2, ".", "");	
									$selisih_akhir =  $dt['discount_total'] - $discount_detail_total;
									//$selisih_akhir =  $dt['discount_total'] - $dt['discount_detail_total'];
									
									//echo 'CEK4 -> '.$product_id.', discount_total = '.$dt['discount_total'].', - discount_detail_total = '.$discount_detail_total.' => discount_billing_detail_total '.$discount_billing_detail_total.', selisih_akhir = '.$selisih_akhir.', data_balancing_diskon_billing => '.$data_balancing_diskon[$product_id]['billing'].', total_discount_balance = '.$balancing_discount_billing[$billing_id]['discount_detail'][$product_id]['total_discount_balance'].'<br/>';

									if($dt['discount_perbilling'] == 1){
										$data_balancing_diskon[$product_id]['billing'] += $selisih_akhir;
									}else{
										$data_balancing_diskon[$product_id]['item'] += $selisih_akhir;
									}
									
									$balancing_discount_billing[$billing_id]['discount_detail'][$product_id]['total_discount_balance'] -= $selisih_akhir;

									//echo 'CEK5 -> '.$product_id.', total_billing = '.$dt_diskon['total_billing'].', selisih_akhir = '.$selisih_akhir.'<br/><br/>';
									
								}
							}
							*/

							//echo '<br/>';

						}
						
					}
				}
				
				//SET SELISIH DISKON
				if(!empty($balancing_discount_billing)){
					foreach($balancing_discount_billing as $billing_id => $dt){
						if(!empty($dt['discount_detail'])){
							//echo 'SSD #'.$billing_id.', discount_perbilling = '.$dt['discount_perbilling'].'<br/>';
							//echo '<pre>';
							//print_r($dt);
							$discount_detail_total = 0;
							foreach($dt['discount_detail'] as $product_id => $dt_diskon){
								
								//$sub_total_balance = $dt_diskon['total_billing'] - $dt_diskon['total_discount'];
								$sub_total_balance = $dt_diskon['total_billing'];
								//echo '$sub_total_balance = '.$sub_total_balance.'<br/>';

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
								
								//echo 'sub_total_balance = '.$sub_total_balance.' <> sub_total = '.$dt_diskon['sub_total'].', sub_total_selisih = '.$sub_total_selisih.' <br/>';

								if(empty($data_selisih_diskon[$product_id])){
									$data_selisih_diskon[$product_id] = 0;
								}
								
								$data_selisih_diskon[$product_id] += $sub_total_selisih;
								
								if(empty($data_selisih_diskon_payment[$product_id])){
									$data_selisih_diskon_payment[$product_id] = array();;
								}
								
								if(empty($data_selisih_diskon_payment[$product_id][$dt['payment_id']])){
									$data_selisih_diskon_payment[$product_id][$dt['payment_id']] = 0;
								}
								
								//echo $product_id.' -> '.$dt['payment_id'].' <br/>';
								$data_selisih_diskon_payment[$product_id][$dt['payment_id']] += $sub_total_selisih;
								
							}
							//echo '<br/>';
						}
					}
				}
			}
			
			
			//echo '<pre>';
			//echo '$data_diskon_awal: <br/>';
			//print_r($data_diskon_awal);
			//echo '$data_balancing_diskon: <br/>';
			//print_r($data_balancing_diskon);
			//echo '$data_balancing_diskon_payment: <br/>';
			//print_r($data_balancing_diskon_payment);
			//echo '$data_selisih_diskon: <br/>';
			//print_r($data_selisih_diskon);
			//echo '$data_selisih_diskon_payment: <br/>';
			//print_r($data_selisih_diskon_payment);
			//echo '$balancing_discount_billing: <br/>';
			//print_r($balancing_discount_billing);
			//echo 'TOTAL = '.count($all_group_date);
			//die();

			//$data_selisih_diskon = array();
			//$data_selisih_diskon_payment = array();
			
			$newData = array();
			if(!empty($all_group_date)){
				foreach($all_group_date as $key => $detail){
					
					$key_mk = strtotime($key);
					//echo $key_mk.' '.date("d-m-Y", $key_mk).'<br/>';
					
					//BALANCING DISKON
					if(!empty($data_diskon_awal[$key])){
						$detail['discount_total'] -= $data_diskon_awal[$key]['item'];
						$detail['discount_billing_total'] -= $data_diskon_awal[$key]['billing'];
					}
					
					if(!empty($data_balancing_diskon[$key])){
						$detail['discount_total'] += $data_balancing_diskon[$key]['item'];
						$detail['discount_billing_total'] += $data_balancing_diskon[$key]['billing'];
					}

					//echo 'sub_total='.$detail['sub_total'].'<br/>';
					//echo 'discount_total='.$detail['discount_total'].'<br/>';
					//echo 'discount_billing_total='.$detail['discount_billing_total'].'<br/>';
					
					$detail['grand_total'] -=$detail['discount_total'];
					$detail['grand_total'] -=$detail['discount_billing_total'];

					//echo 'grandtotal='.$detail['grand_total'].'<br/>';

					
					if(!empty($data_selisih_diskon[$key])){
						//$detail['sub_total'] -= $data_selisih_diskon[$key];
						$detail['grand_total'] -= $data_selisih_diskon[$key];
					}
					
					//BALANCING DISKON PAYMENT
					if(!empty($data_balancing_diskon_payment[$key])){
						foreach($data_balancing_diskon_payment[$key] as $payment_id => $detailP){
							if(!empty($detail['payment_'.$payment_id])){
								$detail['payment_'.$payment_id] -= $detailP;
							}
						}
					}

					if(!empty($data_selisih_diskon_payment[$key])){
						foreach($data_selisih_diskon_payment[$key] as $payment_id => $detailP){
							if(!empty($detail['payment_'.$payment_id])){
								$detail['payment_'.$payment_id] -= $detailP;
							}
						}
					}
					
					
					//KONVERSI PEMBULATAN
					$selisih_pembulatan = 0;
					if(!empty($pembulatan_awal_product[$key])){
						$selisih_pembulatan -= $pembulatan_awal_product[$key];
						$detail['grand_total'] -= $pembulatan_awal_product[$key];
					}
					
					
					if(!empty($konversi_pembulatan_product[$key])){
						$detail['total_pembulatan'] = $konversi_pembulatan_product[$key]['total_pembulatan'];
						$detail['grand_total'] += $konversi_pembulatan_product[$key]['total_pembulatan'];
						$selisih_pembulatan += $konversi_pembulatan_product[$key]['total_pembulatan'];
					}
					
					if(!empty($detail['compliment_total'])){
						$detail['compliment_total'] += $selisih_pembulatan;
					}
					
					//KONVERSI PEMBULATAN PAYMENT
					if(!empty($pembulatan_awal_product_payment[$key])){
						foreach($pembulatan_awal_product_payment[$key] as $payment_id => $detailP){
							if(!empty($detail['payment_'.$payment_id])){
								$detail['payment_'.$payment_id] -= $detailP;
							}
						}
					}
					
					if(!empty($konversi_pembulatan_product_payment[$key])){
						foreach($konversi_pembulatan_product_payment[$key] as $payment_id => $detailP){
							if(!empty($detail['payment_'.$payment_id])){
								$detail['payment_'.$payment_id] += $detailP;
							}
						}
					}
					
					
					$detail['total_food_show'] = priceFormat($detail['total_food']);
					$detail['total_beverage_show'] = priceFormat($detail['total_beverage']);
					$detail['total_other_show'] = priceFormat($detail['total_other']);
					$detail['total_billing_show'] = priceFormat($detail['total_billing']);
					$detail['sub_total_show'] = priceFormat($detail['sub_total']);
					$detail['tax_total_show'] = priceFormat($detail['tax_total']);
					$detail['service_total_show'] = priceFormat($detail['service_total']);
					$detail['total_pembulatan_show'] = priceFormat($detail['total_pembulatan']);
					$detail['grand_total_show'] = priceFormat($detail['grand_total']);
					
					
					$detail['discount_total_show'] = priceFormat($detail['discount_total']);
					$detail['discount_billing_total_show'] = priceFormat($detail['discount_billing_total']);
					$detail['total_dp_show'] = priceFormat($detail['total_dp']);
					$detail['compliment_total_show'] = priceFormat($detail['compliment_total']);
					
					if(!empty($total_hpp[$key])){
						$detail['total_hpp'] = $total_hpp[$key];
					}

					$detail['total_profit'] = $detail['total_billing']-$detail['total_hpp'];
					$detail['total_hpp_show'] = priceFormat($detail['total_hpp']);
					$detail['total_profit_show'] = priceFormat($detail['total_profit']);
						
					
					$newData[$key_mk] = $detail;
					
				}
			}
			
			ksort($newData);
			$data_post['report_data'] = $newData;
			
			
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
			
			$data_post['payment_data'] = $dt_payment_name;
					
		}
		
		
		//DO-PRINT
		if(!empty($do)){
			$data_post['do'] = $do;
		}else{
			$do = '';
		}

		if(empty($useview)){
			$useview = 'print_reportSalesByFoodBeverageRecap';
			$data_post['report_name'] = 'SALES REPORT BY FOOD AND BEVERAGE (RECAP)';
			
			if($do == 'excel'){
				$useview = 'excel_reportSalesByFoodBeverageRecap';
			}
			
		}else{
			$useview = 'print_reportProfitSalesByFoodBeverageRecap';
			$data_post['report_name'] = 'SALES PROFIT REPORT FOOD AND BEVERAGE (RECAP)';
			
			if($do == 'excel'){
				$useview = 'excel_reportProfitSalesByFoodBeverageRecap';
			}
			
		}
		
		$this->load->view('../../billing/views/'.$useview, $data_post);
	}
	
}