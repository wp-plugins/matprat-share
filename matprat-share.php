<?php
/*
Plugin Name: MatPrat Share
Plugin URI: http://www.metronet.no
Description: Enable sharing posts on blogg.matprat.no
Version: 0.1
Author: Thomas Bensmann
Author URI: http://www.bensmann.no
License: GPL2

    Copyright 2012  Thomas Bensmann  (email : thomas@bensmann.no)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

if(!class_exists('MatPrat_Share')){

	class MatPrat_Share{

		private static  $categories = array(
							'frokost',
							'lunsj',
							'middag',
							'småretter',
							'dessert',
							'kake',
							'brød',
							'søt bakst',
							'andre bakevarer',
						),
						$default_cat = 2; //middag

		function __construct(){

			add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
			add_action( 'load-post.php', array( $this, 'setup_meta_box') );
			add_action( 'load-post-new.php', array( $this, 'setup_meta_box' ) );

			if( isset( $_GET['matprat_post_feed_json'] ) ){
				add_action( 'init', array( $this, 'output_posts' ) );
			}

		}


		public static function output_posts(){

			remove_all_filters( 'get_the_exerpt' );
			remove_all_filters( 'the_content' );
			add_filter( 'excerpt_more', array( __CLASS__, 'custom_excerpt_more' ), 999 );
			add_filter( 'excerpt_length', array( __CLASS__, 'custom_excerpt_length' ), 999 );


			global $post;

			$args = array(
				'numberposts' => 8,
				'meta_query' => array(
					array( //matprat_post must equal 'yes'
						'key' => '_matprat_post',
						'value' => 'yes'
					),
					array(	//has to have matprat_category
						'key' => '_matprat_category'
					)
				)
			);

			$posts = get_posts($args);
			$post_data = array();

			foreach($posts as $post){
				$post_data[] = self::prepare_post($post);
			}

			//echo '<pre>'; var_dump($post_data); echo '</pre>';

			echo json_encode($post_data);

			//and were done
			exit();

		}

		public static function prepare_post($post){

			setup_postdata($post);

			//save the data in this object
			$data = new stdClass();

			//Get the permalink
			$data->permalink = get_permalink();

			//Get custom title if it exists
			$custom_title = get_post_meta( $post->ID, '_matprat_post_title', true );
			$data->title = ($custom_title && $custom_title != '') ? $custom_title : get_the_title();
			
			//Get custom excerpt if it exists
			$custom_excerpt = get_post_meta( $post->ID, '_matprat_post_excerpt', true );
			$data->excerpt = ($custom_excerpt && $custom_excerpt != '') ? $custom_excerpt : get_the_excerpt();

			//Get the category
			$data->category = get_post_meta( $post->ID, '_matprat_category', true );

			//Setting a unique post-id
			$data->id = $post->ID;

			//Setting author info
			$data->author_name = get_the_author_meta('display_name');
			$data->author_first_name = get_the_author_meta('first_name');
			$data->author_last_name = get_the_author_meta('last_name');
			$data->author_gravatar = "http://gravatar.com/avatar/" . md5( strtolower( get_the_author_meta( 'email' ) ) );

			//Passing the post date
			$data->date = the_date("Y-m-d H:i:s",'','',false);

			//Get featured image
			if(has_post_thumbnail($post->ID)){
				$thumb_id = get_post_thumbnail_id($post->ID);
				$image_data = wp_get_attachment_image_src($thumb_id,'full');
				$data->image = $image_data[0];
			}
			//Else get first image in post…
			else if($first_image = self::get_first_image()){
				$data->image = $first_image;
			}


			//return the data
			return $data;
		}

		public static function setup_meta_box(){
		    add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box') );
		    add_action( 'save_post', array( __CLASS__, 'save_meta') , 10, 2 );
		}

		public static function custom_excerpt_more(){
			return "…";
		}

		public static function custom_excerpt_length(){
			return 140;
		}

		public static function get_first_image(){
			global $post;
			
			$first_img = false;

			$output = preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $post->post_content, $matches);

			if( count($matches) && count($matches[0]) )
				$first_img = $matches[1][0];

			return $first_img ? $first_img : false;
		}

		public static function register_meta_box(){
			add_meta_box(
				'matprat',
				'Matprat',
				array(
					__CLASS__,
					'meta_box'
				),
				'post',
				'side',
				'high'
			);
		}

		public static function meta_box(){

			global $post; 

			wp_nonce_field( 'matprat_share_meta_nonce', 'matprat_share_nonce' );

			?>

			<p>
				<label for="_matprat_post"><input type="checkbox" name="_matprat_post" id="_matprat_post" <?php if(get_post_meta( $post->ID, '_matprat_post', true ) == 'yes'): ?>checked<?php endif; ?> value="yes" />  <?php _e( 'Del innlegget på Matprat.no:', 'matprat' ); ?></label> 
			</p>
			<p>
				<label for="_matprat_post_title"><?php _e( 'Tittel:', 'matprat' ); ?></label><br />
				<input class="widefat" type="text" name="_matprat_post_title" id="_matprat_post_title" value="<?php echo get_post_meta( $post->ID, '_matprat_post_title', true ); ?>" />  
			</p>
			<p>
				<label for="_matprat_post_excerpt"><?php _e( 'Utdrag:', 'matprat' ); ?></label><br />
				<input class="widefat" type="text" name="_matprat_post_excerpt" id="_matprat_post_excerpt" value="<?php echo get_post_meta( $post->ID, '_matprat_post_excerpt', true ); ?>" />  
			</p>
			<p>
				<h4><?php _e( 'Kategori', 'matprat' ); ?></h4>
				<?php

				//selected category or fallback to default
				if( !($sel_cat = get_post_meta( $post->ID, '_matprat_category', true ) ) )
					$sel_cat = self::$categories[ self::$default_cat ];

				$i = 0; foreach( self::$categories as $cat ): $i++; ?>
					<label for="matprat_category_<?php echo $i; ?>">
						<input type="radio" name="_matprat_category" value="<?php echo $cat; ?>" <?php if($cat == $sel_cat): ?> checked<?php endif;?>>
						<?php echo __($cat, 'matprat'); ?>
					</label><br>
				<?php endforeach; ?>

				<small><?php echo __( 'En kategori må velges for at innlegget skal vises på Matprat.', 'matprat' ); ?></small>
			</p>

			<?php

		}

		private function save_meta_value( $post_id, $name, $default ) {
			$val =  !isset( $_POST[$name] ) ? $default : esc_html( $_POST[$name] );
			return update_post_meta($post_id, $name, $val);
		}

		public static function save_meta($post_id){

			if ( !empty($_POST) && wp_verify_nonce($_POST['matprat_share_nonce'],'matprat_share_meta_nonce') ){

				$params = array(
					array(
						'name' => '_matprat_post',
						'default' => 'no'
					),
					array(
						'name' => '_matprat_category',
						'default' => self::$categories[ self::$default_cat ]
					),
					array(
						'name' => '_matprat_post_excerpt',
						'default' => ''
					),
					array(
						'name' => '_matprat_post_title',
						'default' => ''
					)
				);

				foreach($params as $param)
					self::save_meta_value( $post_id, $param['name'], $param['default'] );

			}
			
		}
	}
}

new MatPrat_Share();
