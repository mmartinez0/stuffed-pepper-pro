<?php
if ( !defined('ABSPATH') ) { die('-1'); }

require_once( SP_PLUGIN_LIB .'sp_list-table.class.php' );

class SP_ProductModule extends SP_Module  {

	public function __construct( $sp ) {
		parent::__construct( $sp, __FILE__, 'product' );
	}

	public function init() {

		add_action( 'wp_ajax_sp_products_get', array( &$this, 'ajax_get_list' ) );

		$this->_page = empty( $_GET['page'] ) ? '' : $_GET['page'];
		$this->_action = empty( $_GET['action'] ) ? '' : $_GET['action'];
		$this->_post_id = empty( $_GET['post'] ) ? '0' : absint( $_GET['post'] );

		$this->register_post_types();
	}	

	public function register_post_types(){

		$this->post_type = 'sp_product';

		$post_type_args = array(
			'description' => 'Stuffed Pepper Products',
			'public' => false,
			'rewrite' => false,
			'supports' => false
		);
		register_post_type( $this->post_type, $post_type_args );

		flush_rewrite_rules();

		$this->fields = array( 
			'post_id',
			'post_title', 
			'post_content', 
			'type', 
			'format', 
			'price', 
			'pdf', 
			'paypal',
			'mailchimp',
			'coupon' );

		$this->types = array( 'Meal Plan' );
		$this->formats = array( 'PDF', 'MailChimp' );
	}

	public function add_submenu_page( $adminmenu ){
		
		$this->page_title = $adminmenu->page_title.' - Products';
		$this->menu_label = 'Products';
		$this->menu_slug = $adminmenu->menu_slug.'-product';
		$this->page_url = admin_url( "admin.php?page=$this->menu_slug" );

		add_submenu_page( $adminmenu->menu_slug, $this->page_title, $this->menu_label, 'administrator', $this->menu_slug, array( $this, 'admin_page' ));

		add_action( "load-stuffer-pepper_page_$this->menu_slug" , array( $this, 'on_load_page' ) );
	}

	public function admin_page() { 
		
		if( $this->_action == 'add' || $this->_action == 'edit' )
			return $this->edit_view();

		return $this->list_view();
	}

	public function list_view(){

		$config = array(

	        'labels' => array (
	                'singular' => __( 'Product', 'stuffed-pepper' ),
	                'plural' => __( 'Products', 'stuffed-pepper' )
	            ),

	        'ajax' => false,

	        'cb' => false,

	        'columns' => array( 
	            'post_title'    => array( 'title' => __( 'Product', 'stuffed-pepper' ),     'callback' => array( $this, 'render_column'), 'sort' => true ),
	            'type'   		=> array( 'title' => __( 'Type', 'stuffed-pepper' ),     	'callback' => null, 'sort' => true ),
	            'format'   		=> array( 'title' => __( 'Format', 'stuffed-pepper' ),     	'callback' => null, 'sort' => true ),
	            'price'  		=> array( 'title' => __( 'Price', 'stuffed-pepper' ),    	'callback' => null, 'sort' => true ),
	            'paypal'   		=> array( 'title' => __( 'PayPal', 'stuffed-pepper' ),     	'callback' => null, 'sort' => true ),
	            'mailchimp'     => array( 'title' => __( 'MailChimp', 'stuffed-pepper' ),   'callback' => null, 'sort' => true ),
	            'pdf'         	=> array( 'title' => __( 'PDF', 'stuffed-pepper' ),      	'callback' => array( $this, 'render_column'), 'sort' => true )
	        ),

	        'bulk_actions' => array(),

	        'per_page' => 10,
	        'orderby' => 'post_title',
	        'order' => 'desc',

	        'prepare_items' => array( $this, 'prepare_items' )
	    );

		$list_table = new SP_List_Table( $config );
		$list_table->prepare_items();

		$this->view( 'products', array( 'list_table' => $list_table ) );

		return true;
	}

	public function edit_view(){

		$coupons = get_posts( array('post_type' => 'sp_coupon' ) );
		$this->coupons = array( '0' => '' );
		foreach( $coupons as $coupon )
			$this->coupons[ $coupon->ID ] = get_post_meta( $coupon->ID, 'code', true );
		$this->result->data['coupons'] = $this->coupons;


		$paypals = get_posts( array('post_type' => 'sp_paypal' ) );
		$this->paypals = array( '0' => '' );
		foreach( $paypals as $paypal )
			$this->paypals[ $paypal->ID ] = $paypal->post_title;
		$this->result->data['paypals'] = $this->paypals;


		$mailchimps = get_posts( array('post_type' => 'sp_mailchimp' ) );
		$this->mailchimps = array( '0' => '' );
		foreach( $mailchimps as $mailchimp )
			$this->mailchimps[ $mailchimp->ID ] = $mailchimp->post_title;
		$this->result->data['mailchimps'] = $this->mailchimps;



		$this->view( 'product', $this->result );
		return true;
	}

	public function admin_enqueue_scripts(){
		wp_enqueue_script( $this->post_type, sprintf( '%s%s.module.js', $this->module_url, $this->post_type ), null, '2.0.0.1', true );
		wp_enqueue_style( $this->post_type, sprintf( '%s%s.module.css', $this->module_url, $this->post_type ) );
	}

	public function ajax_get_list(){
		$result = SP_Result::create();

		$result->send();
	}

	public function on_load_page(){

		$this->result = SP_Result::create();

		if( $_SERVER['REQUEST_METHOD'] == 'GET' ){

			$model = array(
				'post_id' => 0,
				'post_title' => '',
				'post_content' => '',
				'type' => 'Meal Plan',
				'format' => 'MailChimp',

				'price' => '0.00', 
				'paypal' => '0', 
				'mailchimp' => '0', 
				'pdf' => '',
				'coupon' => array()
			);

			if( $this->_post_id > 0 ){

				$post = get_post( $this->_post_id );

				if( $post == null )
					die( "Post # $this->_post_id not found." );


				foreach( $this->fields as $field ){
					$model[ $field ] = get_post_meta( $post->ID, $field, true );
				}

				$model['post_id'] = $post->ID;
				$model['post_title'] = $post->post_title;
				$model['post_content'] = $post->post_content;
			}

			$this->result->with_data( $model );

			return;
		}

		$coupons = $_POST['coupon'];
		$coupons = array_unique( array_diff( $coupons, array( '0' ) ) );

		$model = array(
			'post_id' => SP_Helper::get_post_int( 'post_id' ),
			'post_title' => SP_Helper::get_post_value( 'post_title' ),
			'post_content' => SP_Helper::get_post_value( 'post_content' ),
			'type' => SP_Helper::get_post_value( 'type' ),
			'format' => SP_Helper::get_post_value( 'format' ),
			'price' => SP_Helper::get_post_value( 'price' ),
			'paypal' => SP_Helper::get_post_value( 'paypal' ),
			'mailchimp' => SP_Helper::get_post_value( 'mailchimp' ),
			'pdf' => SP_Helper::get_post_value( 'pdf' ),
			'coupon' => $coupons
		);

		$this->result->with_data( $model );

		$this->save_post( $model );
	}

	public function save_post( $model ){

		$post = array(
			'post_content' => $model['post_content'],
			'post_title' => wp_strip_all_tags( $model['post_title'] ),
			'post_status' => 'publish',
			'post_type' => $this->post_type,
			'ping_status' => 'closed',
			'comment_status' => 'closed'
		);

		if( $model['post_id'] > 0 )
			$post['ID'] = $model['post_id'];

		$result = wp_insert_post( $post, true );

		if( is_a( $result, 'WP_Error' ) ){
			var_dump( $result );
			die();
		}

		foreach( $this->fields as $field ){
			if( count( get_post_meta( $result, $field, false ) ) > 0 )
				update_post_meta( $result, $field, $model[ $field ] );
			else
				add_post_meta( $result, $field, $model[ $field ] );
		}

		if( absint( $model['post_id'] ) != absint( $result ) ){
			wp_redirect( admin_url( "admin.php?page=$this->_page&action=edit&post=$result" ) );
			exit();
		}
	}

	public function prepare_items( $config, $current_page, $posts_per_page, $orderby, $order ){
		
		$posts = get_posts( array('post_type' => $this->post_type ) );

		$total_items = count( $posts );
		$data = array();

		foreach( $posts as $post ) {

			foreach( $this->fields as $field )
				$model[ $field ] = get_post_meta( $post->ID, $field, true );

			$model['ID'] = $post->ID;
			$model['post_id'] = $post->ID;
			$model['post_title'] = $post->post_title;

			$data[] = $model;
		}

		$result = array( 
			'data' => $data,
            'total_items' => $total_items,
            'per_page'    => $posts_per_page,
            'total_pages' => ceil( $total_items / $posts_per_page )
		);

		return $result;
	}

	public function render_column( $list_table, $item, $column_name ){

		if( $column_name == 'post_title' ){
	        $actions = array(
	            'edit' => sprintf( '<a href="?page=%s&action=%s&post=%s">Edit</a>', $_REQUEST['page'], 'edit', $item['post_id'] )
	        );
	        return sprintf( '%1$s <span style="color:silver">(id:%2$s)</span>%3$s', $item[ $column_name ], $item['ID'], $list_table->row_actions( $actions ) );
		}

        return $item[ $column_name ];
	}

}

