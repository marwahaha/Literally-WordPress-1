<?php
/**
 * Table of events list
 * @package literally_wordpress
 */
class LWP_List_Event extends WP_List_Table {
	
	function __construct() {
		parent::__construct(array(
			'singular' => 'event',
			'plural' => 'events',
			'ajax' => false
		));
	}
	
	/**
	 *
	 * @global Literally_WordPress $lwp 
	 */
	function no_items(){
		global $lwp;
		$lwp->e("No matching event is found.");
	}
	
	/**
	 *
	 * @global Literally_WordPress $lwp
	 * @return array 
	 */
	function get_columns() {
		global $lwp;
		$column = array(
			'event_type' => $lwp->_('Event Type'),
			'event_name' => $lwp->_("Event Name"),
			'published' => $lwp->_('Published'),
			'selling_limit' => $lwp->_("Selling Limit"),
			'participants' => $lwp->_('Participants'),
			'actions' => $lwp->_('Actions')
		);
		return $column;
	}
	
	/**
	 * 
	 * @global Literally_WordPress $lwp
	 * @global wpdb $wpdb 
	 */
	function prepare_items() {
		global $lwp, $wpdb, $user_ID;
		//Set column header
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns()
		);
		
		//Set up paging offset
		$per_page = $this->get_per_page();
		$page = $this->get_pagenum(); 
		$offset = ($page - 1) * $per_page;
		
		$sql = <<<EOS
			SELECT SQL_CALC_FOUND_ROWS
				*
			FROM {$wpdb->posts} AS p
			INNER JOIN {$wpdb->postmeta} AS pm
			ON p.ID = pm.post_id AND pm.meta_key = '{$lwp->event->meta_selling_limit}'
EOS;
		//WHERE
		$where = array();
		if($this->get_post_type() != 'all'){
			$where[] = $wpdb->prepare("p.post_type = %s", $this->get_post_type());
		}else{
			$where[] = "p.post_type IN (".implode(',', array_map(create_function('$a', 'return "\'".$a."\'";'), $lwp->event->post_types)).")";
		}
		if(isset($_GET['s']) && !empty($_GET["s"])){
			$where[] = $wpdb->prepare("((p.post_title LIKE %s) OR (p.post_content LIKE %s) OR (p.post_excerpt LIKE %s))", '%'.$_GET["s"].'%', '%'.$_GET["s"].'%', '%'.$_GET["s"].'%');
		}
		$sql .= ' WHERE '.implode(' AND ', $where);
		//ORDER
		$order_by = 'p.post_date';
		if(isset($_GET['orderby'])){
			switch($_GET['orderby']){
				case 'selling_limit':
					$order_by = 'CAST(pm.meta_value AS DATE)';
					break;
			}
		}
		$order = (isset($_GET['order']) && $_GET['order'] == 'asc') ? 'ASC' : 'DESC';
		$sql .= " ORDER BY {$order_by} {$order}";
		$sql .= " LIMIT {$offset}, {$per_page}";
		$this->items = $wpdb->get_results($sql);
		$this->set_pagination_args(array(
			'total_items' => (int) $wpdb->get_var("SELECT FOUND_ROWS()"),
			'per_page' => $per_page
		));
	}
	
	/**
	 * @global Literally_WordPress $lwp
	 * @param Object $item
	 * @param string $column_name
	 * @return string
	 */
	function column_default($item, $column_name){
		global $lwp, $wpdb;
		switch($column_name){
			case 'event_type':
				$post_type_obj = get_post_type_object($item->post_type);
				return $post_type_obj->labels->name;
				break;
			case 'event_name':
				return '<a href="'.admin_url('post.php?post='.$item->ID.'&amp;action=edit').'">'.$item->post_title.'</a>';
				break;
			case 'published':
				return mysql2date(get_option('date_format'), $item->post_date);
				break;
			case 'selling_limit':
				return mysql2date(get_option('date_format'), $item->meta_value);
				break;
			case 'participants':
				$ticket_ids = $lwp->event->get_chicket_ids($item->ID);
				if(empty($ticket_ids)){
					return 0;
				}
				$ticket_ids = join(',', $ticket_ids);
				$sql = <<<EOS
					SELECT COUNT(ID) FROM {$lwp->transaction}
					WHERE book_id IN ({$ticket_ids}) AND status = %s
EOS;
				return $wpdb->get_var($wpdb->prepare($sql, LWP_Payment_Status::SUCCESS));
				break;
			case 'actions':
				return '<a class="button" href="'.admin_url('admin.php?page=lwp-event&amp;event_id='.$item->ID).'">'.$lwp->_('Detail').'</a>';
				break;
		}
	}
	
	/**
	 * @global Literally_WordPress $lwp
	 * @return array
	 */
	function get_sortable_columns() {
		return array(
			'published' => array('published', false),
			'selling_limit' => array('selling_limit', false)
		);
	}
	
	
	/**
	 * Get current page
	 * @return int
	 */
	function get_pagenum() {
		return isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
	}
	
	
	function get_post_type(){
		global $lwp;
		$filter = 'all';
		if(isset($_GET['post_types']) && !$_GET['post_types'] != 'all'){
			$filter = (string)$_GET['post_types'];
		}
		return $filter;
	}
	
	/**
	 *
	 * @return int
	 */
	function get_per_page(){
		$per_page = 20;
		if(isset($_GET['per_page']) && $_GET['per_page'] != 20){
			$per_page = max($per_page, absint($_GET['per_page']));
		}
		return $per_page;
	}
	
	
	function extra_tablenav($which) {
		global $lwp;
		if($which == 'top'):
		?>
		<div class="alignleft acitions">
			<select name="post_types">
				<?php
				$post_types = array('all' => $lwp->_('All Post Types'));
				foreach($lwp->event->post_types as $p){
					$object = get_post_types(array('name' => $p), 'objects');
					foreach($object as $post_type){
						$post_types[$p] = $post_type->labels->name;
					}
				}
				foreach($post_types as $post_type => $label): ?>
					<option value="<?php echo $post_type; if($post_type == $this->get_post_type()) echo '" selected="selected'?>"><?php echo $label; ?></option>
				<?php endforeach; ?>
			</select>
			<select name="per_page">
				<?php foreach(array(20, 50, 100) as $num): ?>
				<option value="<?php echo $num; ?>"<?php if($this->get_per_page() == $num) echo ' selected="selected"';?>>
					<?php printf($lwp->_('%d per 1Page'), $num); ?>
				</option>
				<?php endforeach; ?>
			</select>
			
			<?php submit_button(__('Filter'), 'secondary', '', false); ?>
		</div>
		<?php
		endif;
	}
	
	function get_table_classes() {
		return array_merge(parent::get_table_classes(), array('lwp-table'));
	}
}