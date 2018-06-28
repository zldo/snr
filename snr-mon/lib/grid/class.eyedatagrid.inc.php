<?php
    include_once 'lib/Classes/PHPExcel.php';
/**
 * EyeDataGrid
 * Provides datagrid control features
 *
 * LICENSE: This source file is subject to the BSD license
 * that is available through the world-wide-web at the following URI:
 * http://www.eyesis.ca/license.txt.  If you did not receive a copy of
 * the BSD License and are unable to obtain it through the web, please
 * send a note to mike@eyesis.ca so I can send you a copy immediately.
 *
 * @author     Micheal Frank <mike@eyesis.ca>
 * @copyright  2008 Eyesis
 * @license    http://www.eyesis.ca/license.txt  BSD License
 * @version    v1.1.6 12/3/2008 10:04:44 AM
 * @link       http://www.eyesis.ca/projects/datagrid.html
 * 
 * 2011 mdified by zldo;
 */

 function RowToABC($rowidx) {
  $digits = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $len = strlen($digits);
  $t = floor($rowidx / $len);
  if($t > 0) return RowToABC($t - 1) . $digits{($rowidx % $len)};
  else return $digits{$rowidx % $len};
}

class EyeDataGrid
{
	private $results_per_page = 50;
	private $column_count = 0; // Num of columns
	private $hide_header = false; // Header visibility
	private $hide_footer = false; // Footer visibility
	private $hide_order = false; // Show ordering option
	private $show_checkboxes = false; // Show checkboxes
	private $allow_filters = false; // Allow filters or not
	private $row_select = false; // Enable row selection
	private $create_button = false; // Show create button
	private $reset_button = false; // Show reset grid button
	private $show_row_number = false; // Show row numbers
	private $hide_page_list = false; // Hide page list
	private $page = 1; // Current page
	private $hidden = array(); // Hidden columns
	private $header = array(); // Header titles
        private $columns = array(); // Users columns
	private $type = array(); // Column types
	private $controls = array(); // Row controls, std or custom
	private $order = false; // Current order
	private $filter = false; // Current filter
	private $limit = false; // Current limit
	private $select_where = ''; // Where clause
	private $image_path = ''; // Path to images
        private $col_type_db = array();

	 // Filename of required images
        public $_db, $result; // Database related
	public $img_edit = 'edit.png';
	public $img_delete = 'delete.png';
	public $img_create = 'create.png';
	public $img_reset = 'reset.png';
        public $caption = '';
        public $formaction = '#';
        public $row_count = 0; // Number of rows
        public $UseFormTag = true;
        public $tbl_class = 'tbl';
        public $width = null;
        public $tbl_noresults_class = 'tbl-noresults';
        public $tbl_row_class = 'tbl-row';
        public $tbl_cell_class = 'tbl-cell';
        public $on_tr_custom_tag = null;
        public $on_td_custom_tag = null;


	// Configuration constants
	const CUSCTRL_TEXT = 1;
	const CUSCTRL_IMAGE = 2;
	const STDCTRL_EDIT = 3;
	const STDCTRL_DELETE = 4;
	const TYPE_DATE = 1;
	const TYPE_IMAGE = 2;
	const TYPE_ONCLICK = 3;
	const TYPE_ARRAY = 4;
	const TYPE_DOLLAR = 5;
	const TYPE_HREF = 6;
	const TYPE_CHECK = 7;
	const TYPE_PERCENT = 8;
	const TYPE_CUSTOM = 9;
	const TYPE_FUNCTION = 10;
        const TYPE_FUNCTION_ROW = 11;
        const TYPE_FUNCTION_ARRAY = 12;
	const ORDER_DESC = 'DESC';
	const ORDER_ASC = 'ASC';


	// Default text
	const TXT_RESET = 'Reset Table';
	const TXT_NORESULTS = 'Ничего не найдено!';

	/**
	* Constructor
	*
	* @param EyeMySQLAdap $_db The Eyesis MySQL Adapter class
	* @param string $image_path The path to datagrid images
	*/
	public function __construct(EyeDataSource $_db, $image_path = '')
	{
		$this->_db = $_db;
		if (empty($image_path))
			$this->image_path = 'images/';
		else
			$this->image_path = $image_path;

		$page = (isset($_GET['page'])) ? (int) $_GET['page'] : 0; // Page number
		$order = (isset($_GET['order'])) ? $_GET['order'] : ''; // Order clause
                
                if(isset($_GET['unset_col'])){
                   $un_col=$_GET['unset_col'];
                   unset($_SESSION['arr_filter'][$un_col]); 
                }

                $filter = (isset($_GET['filter'])) ? $_GET['filter'] : ''; // Filter clause
                
                if($filter <> ''){
                        list($column_g, $value_g) = $this->parseInputCond($filter);
                        $_SESSION['arr_filter'][$column_g]=$value_g;
                }

		// Set the limit
		if (empty($page) or $page <= 0)
			$this->setLimit(0, $this->results_per_page);
		else
			$this->page = $page;

		// Set the order
		if ($order)
		{
			list($column, $order) = $this->parseInputCond($order);
			$this->setOrder($column, $order);
		}

		// Set the filter
		if ($filter)
		{
			list($column, $value) = $this->parseInputCond($filter);
			$this->setFilter($column, $value);
		}
	}

	/**
	* Hides page drop down selection and replaces it with text
	*
	* @param $hide Show or hide the page drop down
	*/
	public function hidePageSelectList($hide = true)
	{
		$this->hide_page_list = $hide;
	}

	/**
	 * Allow filters
	 *
	 * @param boolean $allow
	 */
	public function allowFilters($allow = true)
	{
		$this->allow_filters = $allow;
	}

	/**
	 * Hide order functionality
	 *
	 * @param boolean $hide
	 */
	public function hideOrder($hide = true)
	{
		$this->hide_order = $hide;
	}

	/**
	 * Show checkboxes on each row
	 *
	 * @param boolean $show
	 */
	public function showCheckboxes($show = true)
	{
		$this->show_checkboxes = $show;
	}

	/**
	 * Hide header row
	 *
	 * @param boolean $hide
	 */
	public function hideHeader($hide = true)
	{
		$this->hide_header = $hide;
	}

	/**
	 * Hide footer row
	 *
	 * @param boolean $hide
	 */
	public function hideFooter($hide = true)
	{
		$this->hide_footer = $hide;
	}

	/**
	 * Show reset control
	 *
	 * @param string $text Display caption
	 */
	public function showReset($text = self::TXT_RESET)
	{
		$this->reset_button = $text;
	}

	/**
	 * Show row numbers
	 *
	 * @param boolean $show
	 */
	public function showRowNumber($show = true)
	{
		$this->show_row_number = $show;
	}

	/**
	 * Set filter
	 *
	 * @param string $column Column to apply filter clause on
	 * @param string $value Value to compare to
	 */
	private function setFilter($column, $value)
	{
		$this->filter = array('Column' => $column,
							'Value' => $value);
	}

	/**
	 * Set order
	 *
	 * @param string $column Column to apply order clause on
	 * @param string $order Direction, use ORDER_* const
	 */
	private function setOrder($column, $order = self::ORDER_DESC)
	{
		$order = ($order == self::ORDER_DESC)
					? self::ORDER_DESC
					: self::ORDER_ASC;

		$this->order = array('Column' => $column,
							'Order' => $order);
	}

	/**
	* Hides a column
	*
	* @param string $column The column to be hidden
	*/
	public function hideColumn($column)
	{
		$this->hidden[] = $column;
	}

	/**
	* Change column header caption
	*
	* @param string $column The column name
	* @param string $header The new header caption
	*/
	public function setColumnHeader($column, $header)
	{
		$this->header[$column] = $header;
	}
        
        public function AddCol($column, $title, $forcesort = false, $type = 0, $criteria = '', $criteria_2 = '', $col_type_db = '')
        {
            $this->columns[$column] = $forcesort;
            $this->setColumnHeader($column, $title);
            $this->setColumnType($column, $type, $criteria, $criteria_2);
            $this->setTypeColDb($column, $col_type_db);
        }

        public function setTypeColDb($column, $col_type_db = ''){
            $this->col_type_db[$column]=$col_type_db;
        }
        /**
	* Set a column type
	*
	* @param string $column The column to apply the type to
	* @param integer $type The type of column, use TYPE_* const
	* @param mixed $criteria Specific value to each column type
	* @param mixed $criteria_2 Second specific value to each column type
	*/
	public function setColumnType($column, $type, $criteria = '', $criteria_2 = '')
	{
		$this->type[$column] = array($type, $criteria, $criteria_2);
	}

	/**
	* Sets the maximum amount of rows per page
	*
	* @param integer $num Amount of rows per page
	*/
	public function setResultsPerPage($num)
	{
		$this->results_per_page = (int) $num;
		$this->setLimit(0, (int) $num);
	}

	/**
	* Adds a standard control to a row
	*
	* @param integer $type The type of standard control, use STDCTRL_* const
	* @param string $action The action of the control (onclick code or href link)
	* @param integer $action_type The type of action, use TYPE_ONCLICK or TYPE_HREF
	*/
	public function addStandardControl($type, $action, $action_type = self::TYPE_ONCLICK)
	{
		$action = $this->parseLinkAction($action, $action_type);

		switch ($type)
		{
			case self::STDCTRL_EDIT:
				$this->controls[] = '<a ' . $action . '><img src="' . $this->image_path . $this->img_edit . '" alt="Edit" title="Edit" class="tbl-control-image"></a>';
				break;
			case self::STDCTRL_DELETE:
				$this->controls[] = '<a ' . $action . '><img src="' . $this->image_path . $this->img_delete . '" alt="Delete" title="Delete" class="tbl-control-image"></a>';
				break;
			default:
				// Invalid standard control
				break;
		}
	}

	/**
	* Adds a custom control to a row
	*
	* @param integer $type The type of custom control, use CUSCTRL_* const
	* @param string $action The action of the control (onclick code or href link)
	* @param integer $action_type The type of action, use TYPE_ONCLICK or TYPE_HREF
	* @param string $text The textual description of the control
	* @param string $image_path The location of the image if type is CUSCTRL_IMAGE
	*/
	public function addCustomControl($type = self::CUSCTRL_TEXT, $action, $action_type = self::TYPE_ONCLICK, $text, $image_src = '')
	{
		$action = $this->parseLinkAction($action, $action_type);

		switch ($type)
		{
			case self::CUSCTRL_IMAGE:
				$this->controls[] = '<a ' . $action . '><img src="' . $image_src . '" alt="' . $text . '" title="' . $text . '" class="tbl-control-image"></a>';
				break;
			default: // Default to text
				$this->controls[] = '<a ' . $action . '>' . $text . '</a>';
				break;
		}
	}

	/**
	* Adds a create control above the table
	*
	* @param string $action The action associated to the create (onclick code or href link)
	* @param integer $action_type The type of action, use TYPE_ONCLICK or TYPE_HREF
	* @param string $text The textual description of the create
	*/
	public function showCreateButton($action, $action_type = self::TYPE_ONCLICK, $text = 'New Record')
	{
		$action = $this->parseLinkAction($action, $action_type);

		$this->create_button = array('Action' => $action,
										'Text' => $text);
	}

	/**
	* Adds ability to select a entire row
	*
	* @param string $onclick The JS function to call when a row is clicked
	*/
	public function addRowSelect($onclick)
	{
		$this->row_select = $onclick;
	}

	/**
	* Data sanitization and control for filters and ordering
	*
	* @param string $in The value to be sanitized and parsed
	*/
	private function parseInputCond($in)
	{
		return explode(':', ereg_replace("[\'\"\<\>\\]", '%', $in), 2);
	}

	/**
	* Replaces our variables place holders with values
	*
	* @param array $row The row associated array
	* @param string $act The string containing place holders to replace
	* @return string
	*/
	private function parseVariables(array $row, $act)
	{
		// The only way we get an array for $act is for parameters from a column type of function
		if (is_array($act))
		{
			// Loop through each passed param and replace variables where necessary
			foreach ($act as $key => $value)
				$act[$key] = $this->parseVariables($row, $value);

			return $act;
		}


		preg_match_all("/%([A-Za-z0-9_ \-]*)%/", $act, $vars);

		foreach($vars[0] as $v)
			$act = str_replace($v, $row[str_replace('%', '', $v)], $act);

		return $act;
	}

	/**
	* Builds a link action
	*
	* @param string $action The action
	* @param integer $action_type The type of actions (onclick code or href link)
	* @return string
	*/
	private function parseLinkAction($action, $action_type)
	{
		if ($action_type == self::TYPE_ONCLICK)
			$action = 'href="javascript:;" onclick="' . $action . '"';
		else
			$action = 'href="' . $action . '"';

		return $action;
	}

	/**
	* Sets the limit by clause
	*
	* @param integer $low The minimum row number
	* @param integer $high The maximum row number
	*/
	private function setLimit($low, $high)
	{
		$this->limit = array('Low' => $low,
							'High' => $high);
	}

	/**
	* Checks to see if this is an ajax table
	*
	* @return boolean
	*/
	public static function isAjaxUsed()
	{
		if (!empty($_GET['useajax']) and $_GET['useajax'] == 'true')
			return true;

		return false;
	}

	/**
	* Creates the table header
	*
	*/
	private function buildHeader()
	{
		// If entire header is hidden, skip all together
		if ($this->hide_header)
			return;

		echo '<thead><tr>';

		// Get field names of result
		$headers = $this->columns;
		$this->column_count = count($headers);

		// Add a blank column if the row number is to be shown
		if ($this->show_row_number)
		{
			$this->column_count++;
			echo '<th class="tbl-header">#</th>';
		}

		// Show checkboxes
		if ($this->show_checkboxes)
		{
			$this->column_count++;
			echo '<th class="tbl-header tbl-checkall"><input type="checkbox" name="checkall" onclick="tblToggleCheckAll()"></th>';
		}

		// Loop through each header and output it
		foreach ($headers as $t => $column)
		{
			// Skip column if hidden     
			if (in_array($t, $this->hidden))
			{
				$this->column_count--;
				continue;
			}

			// Check for header caption overrides
			if (array_key_exists($t, $this->header))
				$header = $this->header[$t];
			else
				$header = $t;

			if ($this->hide_order or !$this->columns[$t])
				echo '<th class="tbl-header">' . $header; // Prevent the user from changing order
			else {
                            
				if ($this->order and $this->order['Column'] == $t)
					$order = ($this->order['Order'] == self::ORDER_ASC)
										? self::ORDER_DESC
										: self::ORDER_ASC;
				else
					$order = self::ORDER_ASC;

				echo '<th class="tbl-header"><a href="javascript:;" onclick="tblSetOrder(\'' . $t . '\', \'' . $order . '\')">' . $header . "</a>";

				// Show the user the order image if set
				if ($this->order and $this->order['Column'] == $t)
					echo '&nbsp;<img src="' . $this->image_path . 'sort_' . strtolower($this->order['Order']) . '.gif" class="tbl-order">';
			}

			// Add filters if allowed and only if the column type is not "special"
			if ($this->allow_filters and
				(!in_array($this->type[$t][0], array(
									self::TYPE_ARRAY,
									self::TYPE_IMAGE,
									self::TYPE_FUNCTION,
                                                                        self::TYPE_FUNCTION_ROW,
									self::TYPE_DATE,
									self::TYPE_CHECK,
									self::TYPE_CUSTOM,
									self::TYPE_PERCENT
									)))  or ($this->columns[$t] and $this->allow_filters))
			{

                         
                            
				if ($_SESSION['arr_filter'][$t] and !empty($_SESSION['arr_filter'][$t]))
				{
					$filter_display = 'block';
					$filter_value = $this->filter['Value'];
				} else {
					$filter_display = 'none';
					$filter_value = '';
				}

				echo '<a href="javascript:;" onclick="tblShowHideFilter(\'' . $t . '\')"><img src="' . $this->image_path . 'filter.gif" class="tbl-filter-image"></a><br><div class="tbl-filter-box" id="filter-' . $t . '" style="display:' . $filter_display . '"><input type="text" size="6" id="filter-value-' . $t . '" value="'.$_SESSION['arr_filter'][$t].'">&nbsp;<a href="javascript:;" onclick="tblSetFilter(\'' . $t . '\')">фильтр</a></div>';
			}

			echo '</th>';
		}

		// If we have controls, add a blank column
		if (count($this->controls) > 0)
		{
			$this->column_count++;
			echo '<th class="tbl-header">&nbsp;</th>';
		}

		echo '</tr></thead>';
	}

	/**
	* Creates the table footer
	*
	* @param integer $shown The amounts of rows being shown in the current page
	* @param integer $first The row number of the first row
	* @param integer $last The row number of the last row
	*/
	private function buildFooter($shown, $first = 0, $last = 0)
	{
		// Skip adding the footer if it is hidden
		if ($this->hide_footer)
			return;

		$pages = ceil($this->row_count / $this->results_per_page); // Total number of pages

		echo '<tfoot><tr class="tbl-footer"><td class="tbl-nav" colspan="' . $this->column_count . '"><table width="100%" class="tbl-footer"><tr><td width="20%" class="tbl-found">Найдено <em>' . $this->row_count . '</em> строк';

		if ($this->row_count > 0)
			echo ', показаны <em>' . $first . '</em> - <em>' . $last . '</em>';

		echo '</td><td wdith="60%" class="tbl-pages">';

		// Handle results that span multiple pages
		if ($this->row_count > $this->results_per_page)
		{
			if ($this->page > 1)
				echo '<a href="javascript:;" onclick="tblSetPage(1)"><img src="' . $this->image_path . 'arrow_first.gif" class="tbl-arrows" alt="&lt;&lt;" title="First Page"></a><a href="javascript:;" onclick="tblSetPage(' . ($this->page - 1) . ')"><img src="' . $this->image_path . 'arrow_left.gif" class="tbl-arrows" alt="&lt;" title="Previous Page"></a>';
			else
				echo '<img src="' . $this->image_path . 'arrow_first_disabled.gif" class="tbl-arrows" alt="&lt;&lt;" title="First Page"><img src="' . $this->image_path . 'arrow_left_disabled.gif" class="tbl-arrows" alt="&lt;" title="Previous Page">';

			// Special thanks to ionut for this next few lines
			$startpage = ($this->page > 4)
										? $this->page - 4
										: 1;
			
			$endpage = (($pages - 4) > $this->page)
										? $this->page + 4
										: $pages;
                        
                        $endpage = ($this->page <= 4)
										? min(array(9,  $pages))
										: $endpage;

      // Only display a portion of the selectable pages
      for ($i = $startpage; $i <= $endpage; $i++)
			{
				if ($i == $this->page)
					echo '&nbsp;<span class="page-selected"><u><b>' . $i . '</b></u></span>&nbsp;';
				else
					echo '&nbsp;<a href="javascript:;" onclick="tblSetPage(' . $i . ')">' . $i . '</a>&nbsp;';
			}

			if ($this->page < $pages)
				echo '<a href="javascript:;" onclick="tblSetPage(' . ($this->page + 1) . ')"><img src="' . $this->image_path . 'arrow_right.gif" class="tbl-arrows" alt="&gt;" title="Next Page"></a><a href="javascript:;" onclick="tblSetPage(' . $pages . ')"><img src="' . $this->image_path . 'arrow_last.gif" class="tbl-arrows" alt="&gt;&gt;" title="Last Page"></a>';
			else
				echo '<img src="' . $this->image_path . 'arrow_right_disabled.gif" class="tbl-arrows" alt="&gt;" title="Next Page"><img src="' . $this->image_path . 'arrow_last_disabled.gif" class="tbl-arrows" alt="&gt;&gt;" title="Last Page">';
		}

		echo '</td><td width="20%" class="tbl-page">';

		// Only show page section if we have more than one page
		if ($pages > 0)
		{
			echo 'Страница ';
			if (!$this->hide_page_list and $pages > 1)
			{
				// Create a selectable drop down list for pages
				echo '<select name="tbl-page" onchange="tblSetPage(this.options[this.selectedIndex].value)">';
				for ($x = 1; $x <= $pages; $x++)
				{
					echo '<option value="' . $x . '"';
					if ($x == $this->page)
						echo ' selected="selected"';
					echo '>' . $x . '</option>';
				}
				echo '</select>';
			} else
				echo $this->page; // Just write the page number, nothing to fancy

			echo ' из ' . $pages;
		}
                if($this->formaction != "#") echo '<tr><td colspan=' . $this->column_count . ' align=right><input type=submit value="Сохранить изменения"></td></tr>';
		echo '</td></tr></table></td></tr></tfoot>';
	}

	/**
	* Builds row controls
	*
	* @param array $row The row associated array
	*/
	private function buildControls(array $row)
	{
			// Add controls as needed
			if (count($this->controls) > 0)
			{
				echo '<td class="tbl-controls">';
				foreach ($this->controls as $ctl)
					echo $this->parseVariables($row, $ctl);
				echo '</td>';
			}
	}

	/**
	* Outputs the datagrid to the screen
	*
	*/
	public function printTable()
	{
		// Set the limit
		$this->setLimit(($this->page - 1) * $this->results_per_page, $this->results_per_page);

		// FILTER
		$filter_query = '';
		if ($this->select_where)
			$filter_query .= "(" . $this->select_where . ")";

		if (isset($_SESSION['arr_filter']) and (count($_SESSION['arr_filter'])>0))
		{


                            
		/*	if (!strstr($this->filter['Value'], '%'))
				$filter_value = '%' . $this->filter['Value'] . '%';
			else
				$filter_value = $this->filter['Value'];

			if ($this->select_where)
				$filter_query = $filter_query . " AND ";
*/

                    
                    
              /*  $aaa=fopen("123321.txt","a+");
                fwrite($aaa,count($_SESSION['arr_filter']));
                fclose($aaa);*/
                    
                        $add_and='';
                        foreach ($_SESSION['arr_filter'] as $keay_arr=>$value_arr){
                            if($value_arr<>''){

                                
                                if($this->col_type_db[$keay_arr]=='date'){
                                    $pole=explode(".",$value_arr);
                                    if(checkdate($pole[1],$pole[0],$pole[2]))
                                    {
                                        $data_value=$pole[2].'-'.$pole[1].'-'.$pole[0];
                                        $filter_query.=$add_and."(`" . $keay_arr . "` = '" . $data_value . "')";
                                        $add_and=' and ';   
                                    }                                    
                                }else{
                                    $filter_query.=$add_and."(`" . $keay_arr . "` LIKE '%" . $value_arr . "%')";
                                    $add_and=' and ';                                       
                                }
                            }
                        }
                    
                        
			//$filter_query .= "(`" . $this->filter['Column'] . "` LIKE '" . $filter_value . "')";
                        
                        
                        
		}
                
		if ($filter_query)
			$filter = '(' . $filter_query . ')';
                else
                        $filter = '';

		// ORDER
		if ($this->order)
			$order = "`" . $this->order['Column'] . "` " . $this->order['Order'];
		else
			$order = '';

		// LIMIT
		if ($this->limit)
			$limit =  $this->limit['Low'] . ", " . $this->limit['High'];
		else
			$limit = '';

		// Inform the user of any errors. Commonly caused when a column is specified in the filter or order clause that does not exist
		$this->result = $this->_db->DoQuery($filter, $order, $limit);
                

                
                $this->row_count = (int) $this->_db->GetRowCount($filter, $order);
               
		if (!$this->result)
		{
			echo '<div style="color: red; font-weight: bold; border: 2px solid red; padding: 10px;">We ran into a problem while trying to output the table. <a href="javascript:;" onclick="tblReset()">Click here</a> to reset the table or <a href="javascript:;" onclick="alert(\'' . ereg_replace('[\'"]', '', $this->_db->error()) . '\')">here</a> to review the error.</div>';
			return;
		}

		// Count the number of rows without the limit clause

		if (!$this->isAjaxUsed())
		{
			// Print out required javascript functions
			$this->printJavascript();
			echo '<script type="text/javascript">function updateTable() { window.location = "?" + params; }</script>';
		}
                
                if(strpos($this->formaction, '?') > 0) $d = "&";
                else $d = '?';
                // var tblpage = '" . $page . "'; var tblorder = '" . $order . "'; var tblfilter = '" . $filter . "';\n";
                $order = (($this->order) ? implode(':', $this->order) : '');
                $filter = (($this->filter) ? implode(':', $this->filter) : '');
                if($this->UseFormTag){
                    echo '<form method="POST" action="' . $this->formaction . $d . "page=" . $this->page . "&order=" . $order . "&filter=" . $filter .  '" name="dg" id="dg">';
                }    
		// Output the create button
		if ($this->create_button)
			echo '<span class="tbl-create"><a ' . $this->create_button['Action'] . ' title="' . $this->create_button['Text'] . '"><img src="' . $this->image_path . $this->img_create . '" class="tbl-create-image">' . $this->create_button['Text'] . '</a></span>';

		// Output the reset button
		if ($this->reset_button)
			echo '<span class="tbl-reset"><a href="javascript:;" onclick="tblReset()" title="' . $this->reset_button .'"><img src="' . $this->image_path . $this->img_reset . '" class="tbl-reset-image">' . $this->reset_button .'</a></span>';

                if(isset($this->width)){
                    echo '<table class="'.$this->tbl_class.'" width="'.$this->width.'">';
                } else {
                    echo '<table class="'.$this->tbl_class.'">';
                }
                    
		if($this->caption != '') {
                    echo '<caption>' . $this->caption . '</caption>';
                }

		$this->buildHeader();

		echo '<tbody>';

		if ($this->row_count == 0)
			echo '<tr><td colspan="' . $this->column_count . '" class="'.$this->tbl_noresults_class.'">' . self::TXT_NORESULTS . '</td></tr>';
		else {
			$i = 0; $first = 0; $last = 0;

			while ($row = $this->_db->fetchAssoc($this->result))
			{
				echo '<tr class="'.$this->tbl_row_class.' ' . (($i % 2) ? 'odd' : 'even'); // Switch up the bgcolors on each row

				// Handle row selects
				if ($this->row_select)
					echo ' tbl-row-highlight" onclick="' . $this->parseVariables($row, $this->row_select);
                                
                                if(is_callable($this->on_tr_custom_tag))
                                        echo ' ' . $this->on_tr_custom_tag($row);
                                
				echo '">';

				$line = ($this->page == 1)
							? $i + 1
							: $i + 1 + (($this->page - 1) * $this->results_per_page);

				$last = $line; // Last line
				if ($first == 0)
					$first = $line; // First line

				if ($this->show_row_number)
					echo '<th class="tbl-row-num">' . $line . '</th>';

				if ($this->show_checkboxes)
					echo '<td align="center"><input type="checkbox" class="tbl-checkbox" id="checkbox" name="tbl-checkbox" value="' . $row[$this->primary] . '"></td>';

				foreach ($this->columns as $key => $value)
				{
					$value = isset($row[$key])?$row[$key]:'';
                                        // Skip if column is hidden
					if (in_array($key, $this->hidden))
						continue;

					// Apply a column type to the value
					if (array_key_exists($key, $this->type))
					{
                                                
						list($type, $criteria, $criteria_2) = $this->type[$key];
						switch ($type)
						{
							case self::TYPE_ONCLICK:
								if ($value)
									$value = '<a href="javascript:;" onclick="' . $this->parseVariables($row, $criteria) . '">' . $value . '</a>';
								break;

							case self::TYPE_HREF:
								if ($value)
									$value = '<a href="' . $this->parseVariables($row, $criteria) . '">' . $value . '</a>';
								break;

							case self::TYPE_DATE:
								if ($criteria_2 == true)
									$value = date($criteria, strtotime($value));
								else
									$value = date($criteria, $value);
								break;

							case self::TYPE_IMAGE:
								$value = '<img src="' . $this->parseVariables($row, $criteria) . '" id="' . $key . '-' . $i . '">';
								break;

							case self::TYPE_ARRAY:
								$value = $criteria[$value];
								break;

							case self::TYPE_CHECK:
								if ($value == '1' or $value == 'yes' or $value == 'true' or ($criteria != '' and $value == $criteria))
									$value = '<img src="' . $this->image_path . 'check.gif">';
                                                                else 
                                                                        $value = '';
								break;

							case self::TYPE_PERCENT:
								if ($criteria == true)
									$value *= 100; // Value is in decimal format

								$value = round($value); // Round to the nearest decimal

								$value .= '%';

								// Apply a bar if an array is supplied via criteria_2
								if (is_array($criteria_2))
									$value = '<div style="background: ' . $criteria_2['Back'] . '; width: ' . $value . '; color: ' . $criteria_2['Fore'] . ';">' . $value . '</div>';
								break;

							case self::TYPE_DOLLAR:
								$value = '$' . number_format($value, 2);
								break;

							case self::TYPE_CUSTOM:
								$value = $this->parseVariables($row, $criteria);
								break;

							case self::TYPE_FUNCTION:
								//if (is_array($criteria_2))
								//	$value = call_user_func_array($criteria, $this->parseVariables($row, $criteria_2));
								//else
									$value = call_user_func($criteria, $this->parseVariables($row, $criteria_2));

								break;
//функция со всеми полями таблицы                                                            
							case self::TYPE_FUNCTION_ROW:
								//if (is_array($criteria_2))
								//	$value = call_user_func_array($criteria, $this->parseVariables($row, $criteria_2));
								//else
                                                                
								$value = call_user_func($criteria, $row);
                                                           // echo $row[2].' '.$criteria.' ';
								break;      
//функция с несколькими значениями                                                            
							case self::TYPE_FUNCTION_ARRAY:
								//if (is_array($criteria_2))
								//	$value = call_user_func_array($criteria, $this->parseVariables($row, $criteria_2));
								//else
                                                                
								$value = call_user_func_array($criteria, $this->parseVariables($row, $criteria_2));
                                                           // echo $row[2].' '.$criteria.' ';
								break;                                                            

							default:
								// Invalid column type
								break;
							}
					}
                                        if(is_callable($this->on_td_custom_tag)) {
                                            echo '<td class="'.$this->tbl_cell_class.'" '.$this->on_td_custom_tag($key, $row).'>' . $value . '</td>';
                                        } else {
					    echo '<td class="'.$this->tbl_cell_class.'">' . $value . '</td>';
                                        }
				}

				$this->buildControls($row);

				echo '</tr>';

				$i++;
			}
		}
                echo "<input name='tableissent' type=hidden value=1>";
		$this->buildFooter($i, $first, $last);
                echo '</tbody>';
		echo '</table>';
                if($this->UseFormTag){
                    echo '</form>';
                }
	}

        
        
        
	private function buildExcelHeader($pExcel,$nom_row)
	{
            $center = array(
                    'alignment'=>array(
                            'horizontal'=>PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                            'vertical'=>PHPExcel_Style_Alignment::VERTICAL_CENTER
                    )
            );            
		if ($this->hide_header)
			return;
		$headers = $this->columns;
		$this->column_count = count($headers);
                $nom_col=0;
		if ($this->show_row_number)
		{
			$this->column_count++;
                        $pExcel->getActiveSheet()->setCellValueByColumnAndRow(0, $nom_row, "#");
                        $pExcel->getActiveSheet()->getStyle("A".$nom_row)->applyFromArray($center); 
                        $pExcel->getActiveSheet()->getStyle("A".$nom_row)->getFont()->setBold(true);
                        $nom_col++;
		}
		foreach ($headers as $t => $column)
		{
			if (in_array($t, $this->hidden))
			{
				$this->column_count--;
				continue;
			}
			if (array_key_exists($t, $this->header))
				$header = $this->header[$t];
			else
				$header = $t;
                        $pExcel->getActiveSheet()->setCellValueByColumnAndRow($nom_col, $nom_row, $header);
                        $alf_buk=RowToABC($nom_col);
                        $pExcel->getActiveSheet()->getColumnDimension($alf_buk)->setAutoSize(true);
                        $pExcel->getActiveSheet()->getStyle($alf_buk.$nom_row)->applyFromArray($center);        
                        $pExcel->getActiveSheet()->getStyle($alf_buk.$nom_row)->getFont()->setBold(true);                        
                        $nom_col++;
		}
	}        
        
        public function PrintExcel(){
          $pExcel = new PHPExcel();
          $pExcel->setActiveSheetIndex(0);    
          $aSheet = $pExcel->getActiveSheet();         
          $aSheet->setTitle('Первый лист');


  
		// Set the limit
		$this->setLimit(($this->page - 1) * $this->results_per_page, $this->results_per_page);

		// FILTER
		$filter_query = '';
		if ($this->select_where)
			$filter_query .= "(" . $this->select_where . ")";

		if (count($_SESSION['arr_filter'])>0)
		{
                    
                        $add_and='';
                        foreach ($_SESSION['arr_filter'] as $keay_arr=>$value_arr){
                            if($value_arr<>''){

                                
                                if($this->col_type_db[$keay_arr]=='date'){
                                    $pole=explode(".",$value_arr);
                                    if(checkdate($pole[1],$pole[0],$pole[2]))
                                    {
                                        $data_value=$pole[2].'-'.$pole[1].'-'.$pole[0];
                                        $filter_query.=$add_and."(`" . $keay_arr . "` = '" . $data_value . "')";
                                        $add_and=' and ';   
                                    }                                    
                                }else{
                                    $filter_query.=$add_and."(`" . $keay_arr . "` LIKE '%" . $value_arr . "%')";
                                    $add_and=' and ';                                       
                                }
                            }
                        }
                    
                        
			//$filter_query .= "(`" . $this->filter['Column'] . "` LIKE '" . $filter_value . "')";
                        
                        
                        
		}
		
		if ($filter_query)
			$filter = '(' . $filter_query . ')';

		// ORDER
		if ($this->order)
			$order = "`" . $this->order['Column'] . "` " . $this->order['Order'];
		else
			$order = '';

		// LIMIT
		if ($this->limit)
			$limit =  $this->limit['Low'] . ", " . $this->limit['High'];
		else
			$limit = '';

		// Inform the user of any errors. Commonly caused when a column is specified in the filter or order clause that does not exist
		$this->result = $this->_db->DoQuery($filter, $order, $limit);
                

                
                $this->row_count = (int) $this->_db->GetRowCount($filter, $order);
               


		// Count the number of rows without the limit clause


                
                if(strpos($this->formaction, '?') > 0) $d = "&";
                else $d = '?';
                // var tblpage = '" . $page . "'; var tblorder = '" . $order . "'; var tblfilter = '" . $filter . "';\n";
                $order = (($this->order) ? implode(':', $this->order) : '');
                $filter = (($this->filter) ? implode(':', $this->filter) : '');

                $nom_row=1;
                $nom_row_b=1;
                if($this->caption){
                  $pExcel->getActiveSheet()->setCellValueByColumnAndRow(0, $nom_row, $this->caption);
                  $nom_row_b++;
                  $nom_row++;
                }
		$this->buildExcelHeader($pExcel,$nom_row);



		if ($this->row_count == 0){
                    
                }
		else {
			$i = 0; $first = 0; $last = 0;

               

                        
                        if (!$this->hide_header)
                        {
                            $nom_row++;
                        }                                 
			while ($row = $this->_db->fetchAssoc($this->result))
			{

				$line = ($this->page == 1)
							? $i + 1
							: $i + 1 + (($this->page - 1) * $this->results_per_page);

				$last = $line; // Last line
				if ($first == 0)
					$first = $line; // First line
                                $nom_col=0;
				if ($this->show_row_number)
                                {
                                        $pExcel->getActiveSheet()->setCellValueByColumnAndRow($nom_col, $nom_row, $line);
                                        $nom_col++;
                                }        
                                
				foreach ($this->columns as $key => $value)
				{
                                    
					$value = $row[$key];
                                        // Skip if column is hidden
					if (in_array($key, $this->hidden))
						continue;

					// Apply a column type to the value
					if (array_key_exists($key, $this->type))
					{
                                                
						list($type, $criteria, $criteria_2) = $this->type[$key];
						switch ($type)
						{
							case self::TYPE_ONCLICK:
								if ($value)
									$value = '<a href="javascript:;" onclick="' . $this->parseVariables($row, $criteria) . '">' . $value . '</a>';
								break;

							case self::TYPE_HREF:
								if ($value)
									$value = '<a href="' . $this->parseVariables($row, $criteria) . '">' . $value . '</a>';
								break;

							case self::TYPE_DATE:
								if ($criteria_2 == true)
									$value = date($criteria, strtotime($value));
								else
									$value = date($criteria, $value);
								break;

							case self::TYPE_IMAGE:
								$value = '<img src="' . $this->parseVariables($row, $criteria) . '" id="' . $key . '-' . $i . '">';
								break;

							case self::TYPE_ARRAY:
								$value = $criteria[$value];
								break;

							case self::TYPE_CHECK:
								if ($value == '1' or $value == 'yes' or $value == 'true' or ($criteria != '' and $value == $criteria))
									$value = '<img src="' . $this->image_path . 'check.gif">';
								break;

							case self::TYPE_PERCENT:
								if ($criteria == true)
									$value *= 100; // Value is in decimal format

								$value = round($value); // Round to the nearest decimal

								$value .= '%';

								// Apply a bar if an array is supplied via criteria_2
								if (is_array($criteria_2))
									$value = '<div style="background: ' . $criteria_2['Back'] . '; width: ' . $value . '; color: ' . $criteria_2['Fore'] . ';">' . $value . '</div>';
								break;

							case self::TYPE_DOLLAR:
								$value = '$' . number_format($value, 2);
								break;

							case self::TYPE_CUSTOM:
								$value = $this->parseVariables($row, $criteria);
								break;

							case self::TYPE_FUNCTION:
								//if (is_array($criteria_2))
								//	$value = call_user_func_array($criteria, $this->parseVariables($row, $criteria_2));
								//else
									$value = call_user_func($criteria, $this->parseVariables($row, $criteria_2));

								break;
//функция со всеми полями таблицы                                                            
							case self::TYPE_FUNCTION_ROW:
								//if (is_array($criteria_2))
								//	$value = call_user_func_array($criteria, $this->parseVariables($row, $criteria_2));
								//else
                                                                
								$value = call_user_func($criteria, $row);
                                                           // echo $row[2].' '.$criteria.' ';
								break;      
//функция с несколькими значениями                                                            
							case self::TYPE_FUNCTION_ARRAY:
								//if (is_array($criteria_2))
								//	$value = call_user_func_array($criteria, $this->parseVariables($row, $criteria_2));
								//else
                                                                
								$value = call_user_func_array($criteria, $this->parseVariables($row, $criteria_2));
                                                           // echo $row[2].' '.$criteria.' ';
								break;                                                            

							default:
								// Invalid column type
								break;
							}
					}

					
                                        $pExcel->getActiveSheet()->setCellValueByColumnAndRow($nom_col, $nom_row, $value);
                                        $nom_col++;
				}


                                $nom_row++;

				$i++;
			}
                  $alf_buk=RowToABC($nom_col-1); 
                  //$pExcel->getActiveSheet()->getStyle("A1:".$alf_buk.($nom_row-1))->applyFromArray($center);    
                  $pExcel->getActiveSheet()->getStyle("A".$nom_row_b.":".$alf_buk.($nom_row-1))->getBorders()->
                  getAllBorders()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
		}
         
          
          
          
          

          $objWriter = new PHPExcel_Writer_Excel5($pExcel);
          header('Content-Type: application/vnd.ms-excel');
          header('Content-Disposition: attachment;filename="rate.xls"');
          header('Cache-Control: max-age=0');
          $objWriter->save('php://output');   
          exit;
        }

        

	/**
	 * Prints out script to handle Ajax data grids
	 *
	 * @param string $responce Responce script
	 */
	public static function useAjaxTable($responce = '')
	{
		self::printJavascript();

		// If no responce script is set, use the current script
		if (empty($responce))
			$responce = $_SERVER['PHP_SELF'];

		echo "<script type=\"text/javascript\">\n";
		echo "var xmlHttp\n";
		echo "function SetXmlHttpObject() {\n";
		echo "xmlHttp = null;\n";
		echo "try { xmlHttp = new XMLHttpRequest(); }\n";
		echo "catch (e) {\n";
		echo "try { xmlHttp = new ActiveXObject('Msxml2.XMLHTTP'); }\n";
		echo "catch (e) { xmlHttp = new ActiveXObject('Microsoft.XMLHTTP'); } }\n";
		echo "if (xmlHttp == null) {alert('Your web browser does not support Ajax'); }\n";
		echo "return xmlHttp; }\n";
		echo "function stateChanged() { if (xmlHttp.readyState == 4) { document.getElementById('eyedatagrid').innerHTML = xmlHttp.responseText; } }\n";
		echo "function updateTable() { xmlHttp = SetXmlHttpObject(); xmlHttp.onreadystatechange = stateChanged; xmlHttp.open('GET', '" . $responce . "?useajax=true' + params, true); xmlHttp.send(null); }\n";
		echo "</script>\n";
		echo "<div id=\"eyedatagrid\"></div>\n";
		echo "<script type=\"text/javascript\">updateTable();</script>\n";
	}

	/**
	* Prints the required JS functions
	*
	*/
	public function printJavascript()
	{
		if ($this)
		{
			$page = $this->page;
			$order = (($this->order) ? implode(':', $this->order) : '');
			$filter = (($this->filter) ? implode(':', $this->filter) : '');
		}

		echo "<script type=\"text/javascript\">\n";
		echo "var params = ''; var tblpage = '" . $page . "'; var tblorder = '" . $order . "'; var tblfilter = '" . $filter . "';\n";
		echo "function tblSetPage(page) { tblpage = page; params = '&page=' + page + '&order=' + tblorder + '&filter=' + tblfilter; updateTable(); }\n";
		echo "function tblSetOrder(column, order) { tblorder = column + ':' + order; params = '&page=' + tblpage + '&order=' + tblorder + '&filter=' + tblfilter; updateTable(); }\n";
		echo "function tblSetFilter(column) { val = document.getElementById('filter-value-' + column).value; tblfilter = column + ':' + val; tblpage = 1; params = '&page=1&order=' + tblorder + '&filter=' + tblfilter; updateTable(); }\n";
		echo "function tblClearFilter(col_name) { tblfilter = ''; params = '&page=1&order=' + tblorder + '&filter=&unset_col='+col_name; updateTable(); }\n";
		echo "function tblToggleCheckAll() { for (i = 0; i < document.dg.checkbox.length; i++) { document.dg.checkbox[i].checked = !document.dg.checkbox[i].checked; } }\n";
		echo "function tblShowHideFilter(column) { var o = document.getElementById('filter-' + column); if (o.style.display == 'block') { tblClearFilter(column); } else {	o.style.display = 'block'; } }\n";
		echo "function tblReset() { params = '&page=1'; updateTable(); }\n";
		echo "</script>\n";
	}
}
