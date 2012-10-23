<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
 
/*
// to use functions from mod.cartthrob.php file
require_once PATH_THIRD.'cartthrob/mod.cartthrob.php';
// then instatiate it: $mod = new Cartthrob;
// call methods as needed $mod->method(); 
*/

class Cartthrob_credits_add_ext
{
	public $settings = array();
	public $name = 'CartThrob - Adds credits';
	public $version = '1.0.0';
	public $description = 'Adds credits to a members account based on cart total. Update the conversion rate in the extension settings. Defaults to 100.';
	public $settings_exist = 'y';
	public $docs_url = 'http://barrettnewton.com';
 	protected $EE;

	public function __construct($settings = '')
	{
		$this->EE =& get_instance();
		
		$this->settings = $settings;
	}

	public function activate_extension()
	{
		$this->settings = array(
	        'credits_per_dollar'   => "100",
	    );
		$this->EE->db->insert(
			'extensions',
			array(
				'class' => __CLASS__,
				'method' => 'cartthrob_on_authorize',
				'hook' 	=> 'cartthrob_on_authorize',
				'settings' => '',
				'priority' => 10,
				'version' => $this->version,
				'enabled' => 'y'
			)
		);
	}
	
	public function update_extension($current='')
	{
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}

		$this->EE->db->update(
			'extensions',
			array('version' => $this->version),
			array('class' => __CLASS__)
		);
	}

	public function disable_extension()
	{
		$this->EE->db->delete('extensions', array('class' => __CLASS__));
	}
	function settings()
	{
	    $settings = array();

 	    $settings['credits_per_dollar']      = array('i', '', "100");

	    return $settings;
	}
	public function cartthrob_on_authorize()
	{
		if ($this->EE->db->table_exists('exp_cartthrob_cartthrob_credits')) 
		{   
			$this->EE->load->helper('data_formatting'); 
			
			$credits_per_dollar = sanitize_number($this->settings['credits_per_dollar']); 
 			
			$total = $this->EE->cartthrob->cart->order('total'); 
			
			// if credits have been added to the basket, we'll remove them from the total
			foreach ( $this->EE->cartthrob->cart->order('items') as $item )
			{
				// Calculate the credits.
				if (!empty($item['meta']['credits']) && is_bool($item['meta']['credits']))
				{
					// applying price to the conversion rate
					$total -= ($item['price'] * $item['quantity'] );
				}
				elseif(!empty($item['meta']['credits']))
				{
					// otherwise just adding the preset credit value * quantity
					$total -=($item['meta']['credits'] * $item['quantity'] ) ; 
				}
			}
				
			$credits = number_format( (double) (($total * $credits_per_dollar)) , 2, ".","");

			$this->EE->load->model('generic_model');
			$model = new Generic_model($table_name = 'cartthrob_credits_credits');

			$member_data = (array) $model->read($id=NULL,$order_by=NULL,$order_direction='asc',$field_name="member_id",$this->EE->cartthrob->cart->order('member_id'),$limit="1"); 	

			if (!empty($member_data) )
			{
				foreach($member_data as $key => $update_data)
				{
					$update_data['credits'] +=  $credits;
					$model->update($update_data['id'], $update_data);
				}
			}
			// no member data. create some
			else
			{
				$model->create(array('member_id' => $this->EE->cartthrob->cart->order('member_id'), 'credits' => $credits));
			}
		}
 	}
}
//END CLASS