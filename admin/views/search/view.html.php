<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            http://www.flexicontent.com
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentViewBaseRecords', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/base/view_records.php');

/**
 * View class for the FLEXIcontent search indexes screen
 */
class FLEXIcontentViewSearch extends FlexicontentViewBaseRecords
{
	var $proxy_option   = null;
	var $title_propname = null;
	var $state_propname = null;
	var $db_tbl         = null;

	public function display($tpl = null)
	{
		/**
		 * Initialise variables
		 */

		global $globalcats;
		$app      = JFactory::getApplication();
		$jinput   = $app->input;
		$document = JFactory::getDocument();
		$user     = JFactory::getUser();
		$cparams  = JComponentHelper::getParams('com_flexicontent');
		$session  = JFactory::getSession();
		$db       = JFactory::getDbo();

		$option   = $jinput->getCmd('option', '');
		$view     = $jinput->getCmd('view', '');
		$task     = $jinput->getCmd('task', '');
		$layout   = $jinput->getString('layout', 'default');

		if ($layout === 'indexer')
		{
			return parent::display($tpl);
		}

		$isAdmin  = $app->isAdmin();
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		// Some flags & constants
		;

		// Load Joomla language files of other extension
		if (!empty($this->proxy_option))
		{
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, null, true);
		}

		// Get model
		$model = $this->getModel();

		// Performance statistics
		if ($print_logging_info = $cparams->get('print_logging_info'))
		{
			global $fc_run_times;
		}


		/**
		 * Get filters and ordering
		 */

		$count_filters = 0;

		// Order and order direction
		$filter_order      = $model->getState('filter_order');
		$filter_order_Dir  = $model->getState('filter_order_Dir');

		// Various filters
		$filter_indextype = $model->getState('filter_indextype');
		$isADV = $filter_indextype === 'advanced';

		$filter_fieldtype = $model->getState('filter_fieldtype');
		$filter_type      = $model->getState('filter_type');
		$filter_state     = $model->getState('filter_state');

		if ($filter_fieldtype) $count_filters++;
		if ($filter_type) $count_filters++;
		if ($filter_state) $count_filters++;

		// Text search
		$search = $model->getState('search');
		$search = StringHelper::trim(StringHelper::strtolower($search));


		$search_itemtitle	= $model->getState( 'search_itemtitle' );
		$search_itemid		= $model->getState( 'search_itemid' );
		$search_itemid		= !empty($search_itemid) ? (int)$search_itemid : '';
		if ($search_itemtitle) $count_filters++; if ($search_itemid) $count_filters++;

		$filter_indextype	= $model->getState( 'filter_indextype' );

		$f_active['filter_fieldtype']	= (boolean)$filter_fieldtype;
		$f_active['filter_type']	= (boolean)$filter_type;
		$f_active['filter_state']	= (boolean)$filter_state;

		$f_active['search']			= strlen($search);
		$f_active['search_itemtitle']	= strlen($search_itemtitle);
		$f_active['search_itemid']		= (boolean)$search_itemid;


		/**
		 * Add css and js to document
		 */

		if ($layout !== 'indexer')
		{
			// Add css to document
			if ($isAdmin)
			{
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
					: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
					: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);
			}
			else
			{
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH)
					: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent_rtl.css', FLEXI_VHASH);
			}

			// Add JS frameworks
			flexicontent_html::loadFramework('select2');

			// Load custom behaviours: form validation, popup tooltips
			JHtml::_('behavior.formvalidation');
			JHtml::_('bootstrap.tooltip');

			// Add js function to overload the joomla submitform validation
			$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
			$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
		}


		/**
		 * Create Submenu & Toolbar
		 */

		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu('CanIndex');

		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_SEARCH_INDEX' );
		$site_title = $document->getTitle();
		JToolbarHelper::title( $doc_title, FLEXI_J16GE ? 'searchtext.png' : 'searchindex' );
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$this->setToolbar();


		/**
		 * Get data from the model
		 */

		if ( $print_logging_info )  $start_microtime = microtime(true);

		// Get item types
		$types = $this->get('Typeslist');

		// Get field types
		$fieldtypes = flexicontent_db::getFieldTypes($_grouped=false, $_usage=true, $_published=false);

		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;



		/**
		 * Create List Filters
		 */

		$lists = array();

		$js = '';

		//build backend visible filter
		if ($isADV)
		{
			$fftypes = array();
			$fftypes[] = JHtml::_('select.option',  '', '-' /*JText::_( 'FLEXI_ALL_FIELDS_TYPE' )*/ );
			$fftypes[] = JHtml::_('select.option',  'C', JText::_( 'FLEXI_CORE_FIELDS' ) );
			$fftypes[] = JHtml::_('select.option',  'NC', JText::_( 'FLEXI_CUSTOM_NON_CORE_FIELDS' ) );
			foreach ($fieldtypes as $field_type => $ftdata) {
				$fftypes[] = JHtml::_('select.option', $field_type, '-'.$ftdata->assigned.'- '. $field_type);
			}

			$lists['filter_fieldtype'] = ($filter_fieldtype || 1 ? '<div class="add-on">'.JText::_('FLEXI_FIELD_TYPE').'</div>' : '').
				JHtml::_('select.genericlist', $fftypes, 'filter_fieldtype', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_fieldtype );
		}

		//build type select list
		$lists['filter_type'] = ($filter_type|| 1 ? '<div class="add-on">'.JText::_('FLEXI_TYPE').'</div>' : '').
			flexicontent_html::buildtypesselect($types, 'filter_type', $filter_type, '-'/*true*/, 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'filter_type');

		//publish unpublished filter
		$ffstates = array();
		$ffstates[] = JHtml::_('select.option',  '', '-' /*JText::_( 'FLEXI_SELECT_STATE' )*/ );
		$ffstates[] = JHtml::_('select.option',  'ALL_P', JText::_('FLEXI_GRP_PUBLISHED') . ' ' . JText::_('FLEXI_STATE_S'));
		$ffstates[] = JHtml::_('select.option',  'ALL_U', JText::_('FLEXI_GRP_UNPUBLISHED') . ' ' . JText::_('FLEXI_STATE_S'));

		$lists['filter_state'] = ($filter_state || 1 ? '<div class="add-on">'.JText::_('FLEXI_STATE').'</div>' : '').
			JHtml::_('select.genericlist', $ffstates, 'filter_state', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_state );

		// build filter index type record listing
		$itn['basic'] = JText::_( 'FLEXI_INDEX_BASIC' );
		$itn['advanced'] = JText::_( 'FLEXI_INDEX_ADVANCED' );
		$indextypes = array();
		//foreach ($itn as $i => $v) $indextypes[] = JHtml::_('select.option', $i, $v);
		//$lists['filter_indextype'] = JHtml::_('select.radiolist', $indextypes, 'filter_indextype', 'size="1" class="inputbox" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_indextype );

		$lists['filter_indextype'] = '';
		foreach ($itn as $i => $v)
		{
			$checked = $filter_indextype == $i ? ' checked="checked" ' : '';
			$lists['filter_indextype'] .= '<input type="radio" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" class="inputbox" size="1" '.$checked.' value="'.$i.'" id="filter_indextype'.$i.'" name="filter_indextype" />';
			$lists['filter_indextype'] .= '<label class="" id="filter_indextype'.$i.'-lbl" for="filter_indextype'.$i.'">'.$v.'</label>';
		}

		// Table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']     = $filter_order;

		// Search index & item title filter
		$lists['search']= $search;
		$lists['search_itemtitle']= $search_itemtitle;
		$lists['search_itemid']= $search_itemid;

		$rows   = $this->get('Data');  // MUST BE BEFORE getCount and getPagination because it also calculates total rows
		$total  = $this->get('Count');
		$pagination = $this->get('Pagination');
		$limitstart = $this->get('LimitStart');

		if ($filter_fieldtype)
		{
			$js .= "jQuery('.col_fieldtype').addClass('filtered_column');";
		}

		if ($search)
		{
			$js .= "jQuery('.col_search').addClass('filtered_column');";
		}

		if ($js)
		{
			$document->addScriptDeclaration('
				jQuery(document).ready(function(){
					' . $js . '
				});
			');
		}

		$query = "SHOW VARIABLES LIKE '%ft_min_word_len%'";
		$db->setQuery($query);
		$_dbvariable = $db->loadObject();
		$ft_min_word_len = (int) @ $_dbvariable->Value;
		$notice_ft_min_word_len	= $app->getUserStateFromRequest( $option.'.fields.notice_ft_min_word_len',	'notice_ft_min_word_len',	0, 'int' );
		//if ( $cparams->get('show_usability_messages', 1) )     // Important usability messages
		//{

		$old_add_search_prefix = $app->getUserState('add_search_prefix', null);

		$add_search_prefix = $cparams->get('add_search_prefix', 0);
		$app->setUserState('add_search_prefix', $add_search_prefix);


		if ($old_add_search_prefix !== null && $old_add_search_prefix != $add_search_prefix) {
			$app->enqueueMessage('Parameter: "Searching small/common words" has changed, please recreate (just once) the search indexes, otherwise text search will not work', 'warning');
		}

		// Important usability messages
		if (!$cparams->get('add_search_prefix', 0))
		{
			if ($ft_min_word_len > 1 && $notice_ft_min_word_len < 10)
			{
				$app->setUserState( $option.'.fields.notice_ft_min_word_len', $notice_ft_min_word_len+1 );
				$app->enqueueMessage("NOTE : Database limits minimum search word length (ft_min_word_len) to ".$ft_min_word_len, 'message');
				$app->enqueueMessage('Please enable: "Searching small/common words":
					<a class="btn" href="index.php?option=com_config&view=component&component=com_flexicontent&path=&"><span class="icon-options"></span>Configuration</a>
					and then click to re-INDEX both search indexes', 'notice');
			}
		}



		$this->count_filters = $count_filters;

		$this->lists       = $lists;
		$this->rows        = $rows;
		$this->pagination  = $pagination;
		$this->total = $total;
		$this->limitstart = $limitstart;
		$this->f_active = $f_active;

		$this->perms  = FlexicontentHelperPerm::getPerm();
		$this->option = $option;
		$this->view   = $view;
		$this->state  = $this->get('State');
		$this->isADV = $isADV;

		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;


		/**
		 * Render view's template
		 */

		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }

		parent::display($tpl);

		if ( $print_logging_info ) @$fc_run_times['template_render'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}



	/**
	 * Method to configure the toolbar for this view.
	 *
	 * @access	public
	 * @return	void
	 */
	function setToolbar()
	{
		$user     = JFactory::getUser();
		$document = JFactory::getDocument();
		$toolbar  = JToolbar::getInstance('toolbar');
		$perms    = FlexicontentHelperPerm::getPerm();
		$session  = JFactory::getSession();

		$js = '';

		$contrl = "search.";
		$contrl_s = null;

		$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);
		$tip_class = ' hasTooltip';

		$btn_arr = array();

		if ($perms->CanIndex)
		{
			$popup_load_url = JUri::base(true) . '/index.php?option=com_flexicontent&amp;view=search&amp;layout=indexer&amp;tmpl=component&amp;indexer=basic';
			$btn_text = JText::_('FLEXI_REINDEX_BASIC_CONTENT_LISTS');
			$btn_name = 'search_adv_index_dirty_only';
			$full_js="if (!confirm('" . str_replace('<br>', '\n', flexicontent_html::encodeHTML(JText::_('FLEXI_MAY_TAKE_TIME_IN_LARGE_WEBSITES'), 'd')) . "')) return false; var url = jQuery(this).data('taskurl'); fc_showDialog(url, 'fc_modal_popup_container', 0, 550, 350, function(){document.body.innerHTML='<span class=\"fc_loading_msg\">"
						.$loading_msg."<\/span>'; window.location.reload(false)}, {'title': '".flexicontent_html::encodeHTML(JText::_('FLEXI_REINDEX_BASIC_CONTENT_LISTS'), 'd')."'}); return false;";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				$btn_text, $btn_name, $full_js,
				$msg_alert = JText::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = '',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				'btn btn-fcaction ' . $tip_class, 'icon-loop',
				'data-placement="right" data-taskurl="' . $popup_load_url .'" title=""', $auto_add = 0, $tag_type='button')
				;

			$popup_load_url = JUri::base(true) . '/index.php?option=com_flexicontent&amp;view=search&amp;layout=indexer&amp;tmpl=component&amp;indexer=advanced';
			$btn_text = JText::_('FLEXI_REINDEX_ADVANCED_SEARCH_VIEW');
			$btn_name = 'search_adv_index_dirty_only';
			$full_js="if (!confirm('" . str_replace('<br>', '\n', flexicontent_html::encodeHTML(JText::_('FLEXI_MAY_TAKE_TIME_IN_LARGE_WEBSITES'), 'd')) . "')) return false; var url = jQuery(this).data('taskurl'); fc_showDialog(url, 'fc_modal_popup_container', 0, 550, 350, function(){document.body.innerHTML='<span class=\"fc_loading_msg\">"
						.$loading_msg."<\/span>'; window.location.reload(false)}, {'title': '".flexicontent_html::encodeHTML(JText::_('FLEXI_REINDEX_ADVANCED_SEARCH_VIEW'), 'd')."'}); return false;";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				$btn_text, $btn_name, $full_js,
				$msg_alert = JText::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = '',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				'btn btn-fcaction ' . $tip_class, 'icon-loop',
				'data-placement="right" data-taskurl="' . $popup_load_url .'" title=""', $auto_add = 0, $tag_type='button')
				;

			$popup_load_url = JUri::base(true) . '/index.php?option=com_flexicontent&amp;view=search&amp;layout=indexer&amp;tmpl=component&amp;indexer=advanced&amp;rebuildmode=quick';
			$btn_text = JText::_('FLEXI_REINDEX_ADVANCED_SEARCH_VIEW_DIRTY_ONLY');
			$btn_name = 'search_adv_index_dirty_only';
			$full_js="if (!confirm('" . str_replace('<br>', '\n', flexicontent_html::encodeHTML(JText::_('FLEXI_MAY_TAKE_TIME_IN_LARGE_WEBSITES'), 'd')) . "')) return false; var url = jQuery(this).data('taskurl'); fc_showDialog(url, 'fc_modal_popup_container', 0, 550, 350, function(){document.body.innerHTML='<span class=\"fc_loading_msg\">"
						.$loading_msg."<\/span>'; window.location.reload(false)}, {'title': '".flexicontent_html::encodeHTML(JText::_('FLEXI_REINDEX_ADVANCED_SEARCH_VIEW_DIRTY_ONLY'), 'd')."'}); return false;";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				$btn_text, $btn_name, $full_js,
				$msg_alert = JText::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = '',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				'btn btn-fcaction ' . $tip_class, 'icon-loop',
				'data-placement="right" data-taskurl="' . $popup_load_url .'" title=""', $auto_add = 0, $tag_type='button')
				;
		}

		if (count($btn_arr))
		{
			$drop_btn = '
				<button type="button" class="' . $this->btn_sm_class . ' btn-primary dropdown-toggle" data-toggle="dropdown">
					<span title="'.JText::_('FLEXI_SEARCH_INDEXES').'" class="icon-menu"></span>
					'.JText::_('FLEXI_SEARCH_INDEXES').'
					<span class="caret"></span>
				</button>';
			array_unshift($btn_arr, $drop_btn);
			flexicontent_html::addToolBarDropMenu($btn_arr, 'search-index-btns-group', ' ');
		}

		//$toolbar->appendButton('Confirm', 'FLEXI_DELETE_INDEX_CONFIRM', 'trash', 'FLEXI_INDEX_ADVANCED_PURGE', $contrl . 'purge', false);
		$btn_icon = 'icon-trash';
		$btn_name = 'purge';
		$btn_task = $contrl . 'purge';
		$extra_js = "";
		flexicontent_html::addToolBarButton(
			'FLEXI_INDEX_ADVANCED_PURGE',
			$btn_name, $full_js='', $msg_alert='', $msg_confirm=JText::_('FLEXI_PURGE_INDEX_CONFIRM'),
			$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="", $btn_icon);

		//$toolbar->appendButton('Confirm', 'Update ?', 'shuffle', 'FLEXI_UPDATE_CUSTOM_ORDER_INDEXES', $contrl . 'custom_order', false);
		$btn_icon = 'icon-shuffle';
		$btn_name = 'custom_order';
		$btn_task = $contrl . 'custom_order';
		$extra_js = "";
		flexicontent_html::addToolBarButton(
			'FLEXI_UPDATE_CUSTOM_ORDER_INDEXES',
			$btn_name, $full_js='', $msg_alert='', $msg_confirm=JText::_('FLEXI_UPDATE_CUSTOM_ORDER_INDEXES'),
			$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="", $btn_icon);

		// Configuration button
		if ($perms->CanConfig)
		{
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}

		if ($js)
		{
			$document->addScriptDeclaration('
				jQuery(document).ready(function(){
					' . $js . '
				});
			');
		}
	}
}
