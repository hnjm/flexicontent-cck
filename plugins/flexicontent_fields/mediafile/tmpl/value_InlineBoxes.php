<?php
use Joomla\String\StringHelper;
$isAdmin = JFactory::getApplication()->isClient('administrator');

// Important create a -1 "value", before any other normal values, so that it is at 1st position of the array
$field->{$prop}[-1] = '';

$field->url = array();
$field->abspath = array();
$field->file_data = array();
$field->hits_total = 0;

$per_value_js = "";
$n = 0;
$i = 0;

foreach($values as $file_id)
{
	// Skip empty value but add empty placeholder if inside fieldgroup
	if (empty($file_id) || !isset($files_data[$file_id]))
	{
		if ($is_ingroup)
		{
			$field->{$prop}[$n++] = '';
		}
		continue;
	}
	$file_data = $files_data[$file_id];
	$FN_n      = $field_name_js.'_'.$n;


	// ***
	// *** Check if it exists and get file size
	// ***

	$basePath = $file_data->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;
	$abspath = str_replace(DS, '/', JPath::clean($basePath.DS.$file_data->filename));

	$_size = '-';

	if ($display_size)
	{
		if ($file_data->url)
		{
			$_size = (int)$file_data->size ? (int)$file_data->size : '-';
		}
		elseif (file_exists($abspath))
		{
			$_size = filesize($abspath);
		}

		// Override DB size with the calculated file size
		$file_data->size = (int) $_size;
	}

	// Force new window for URLs that have zero file size
	$non_file_url = $file_data->url && !$file_data->size;


	// ***
	// *** Check user access on the file
	// ***

	$authorized = true;
	$is_public  = true;
	if ( !empty($file_data->access) )
	{
		$authorized = in_array($file_data->access,$aid_arr);
		$is_public  = in_array($public_acclevel,$aid_arr);
	}

	// If no access and set not to show 'no-access' message then skip the value, if not in field group
	if ( !$authorized && !$noaccess_display )
	{
		// Some extra data for developers: (absolute) file URL and (absolute) file path
		if ($is_ingroup)
		{
			$field->{$prop}[$n++]	=  '';
		}
		continue;
	}

	// Initialize CSS classes variable
	$file_classes = !$authorized ? 'fcfile_noauth' : '';


	// ***
	// *** Prepare displayed information
	// ***

	// a. ICON: create it according to filetype
	$icon = '';
	if ($useicon)
	{
		$file_data = $this->addIcon( $file_data );
		$_tooltip_title   = '';
		$_tooltip_content = JText::_( 'FLEXI_FIELD_FILE_TYPE', true ) .': '. $file_data->ext;
		$icon = '
		<span class="fcfile_mime">
			' . JHtml::image($file_data->icon, $file_data->ext, 'class="fcicon-mime '.$tooltip_class.'" title="'.JHtml::tooltipText($_tooltip_title, $_tooltip_content, 1, 0).'"') . '
		</span>';
	}

	// b. LANGUAGE: either as icon or as inline text or both
	$lang = '';
	$file_data->language = $file_data->language == '' ? '*' : $file_data->language;

	// Also show 'ALL' language
	if ($display_lang)
	{
		$lang = '<span class="fcfile_lang fc-iblock">';

		$lang .= $display_lang == 1 || $display_lang == 3 ? '<span class="icon-flag fcicon-lang"></span> ' : '';
		$lang .= $display_lang == 2 || $display_lang == 3 ? '<span class="fcfile_lang_label label">' .JTEXT::_('FLEXI_LANGUAGE'). '</span> ' : '';
		$lang .=
		'<span class="fcfile_lang_value value">'
			. ($file_data->language === '*' ? JText::_('FLEXI_FIELD_FILE_ALL_LANGS') : $langs->{$file_data->language}->name) .
		'</span>';

		$lang .= '</span>';
	}


	/**
	 * c. SIZE: in KBs / MBs
	 */
	$sizeinfo = '';

	if ($display_size)
	{
		$sizeinfo .= '
		<span class="fcfile_size fc-iblock">';

		$sizeinfo .= $display_size == 1 || $display_size == 3 ? '<span class="icon-archive fcicon-size"></span> ' : '';
		$sizeinfo .= $display_size == 2 || $display_size == 3 ? '<span class="fcfile_size_label label">' . JTEXT::_('FLEXI_FIELD_FILE_SIZE') . '</span> ' : '';

		if (!is_numeric($_size))
		{
			$sizeinfo .= '<span class="fcfile_size_value value">' . $_size . '</span>';
		}
		elseif ($_size < 1048576)
		{
			$sizeinfo .= '<span class="fcfile_size_value value">' . number_format($_size / 1024, 0) . '&nbsp;'.JTEXT::_('FLEXI_FIELD_FILE_KBS').'</span>';
		}
		elseif ($_size < 1073741824)
		{
			$sizeinfo .= '<span class="fcfile_size_value value">' . number_format($_size / 1048576, 2) . '&nbsp;'.JTEXT::_('FLEXI_FIELD_FILE_MBS').'</span>';
		}
		else
		{
			$sizeinfo .= '<span class="fcfile_size_value value">' . number_format($_size / 1073741824, 2) . '&nbsp;'.JTEXT::_('FLEXI_FIELD_FILE_GBS').'</span>';
		}

		$sizeinfo .= '</span>';
	}


	/**
	 * d. HITS: either as icon or as inline text or both
	 */
	$hits = '';

	if ($display_hits)
	{
		$hits = '
		<span class="fcfile_hits fc-iblock">';

		$hits .= $display_hits == 1 || $display_hits == 3 ? '<span class="icon-eye fcicon-hits"></span> ' : '';
		$hits .= $display_hits == 2 || $display_hits == 3 ? '<span class="fcfile_hits_label label">' .JTEXT::_('FLEXI_FIELD_FILE_HITS'). '</span> ' : '';
		$hits .= '<span class="fcfile_hits_value value">'.$file_data->hits.'</span>';

		$hits .= '</span>';
	}

	$field->hits_total += $file_data->hits;


	// e. FILENAME / TITLE: decide whether to show it (if we do not use button, then displaying of filename is forced)
	$filetitle = $file_data->altname ? $file_data->altname : $file_data->filename;
	$filetitle_escaped = htmlspecialchars($filetitle, ENT_COMPAT, 'UTF-8');

	if ($lowercase_filename) $filetitle = StringHelper::strtolower( $filetitle );
	$filename_original = $file_data->filename_original ? $file_data->filename_original : $file_data->filename;

	$name_str = $display_filename==2 ? $filename_original : $filetitle;
	$name_escaped = htmlspecialchars($name_str, ENT_COMPAT, 'UTF-8');

	$name_classes = $file_classes . ' fcfile_title';
	$name_html = '<span class="' . $name_classes . '">'. $name_str . '</span>';


	// f. DESCRIPTION: either as tooltip or as inline text
	$descr_tip = $descr_inline = $descr_icon = '';
	if (!empty($file_data->description))
	{
		// Not authorized
		if (!$authorized)
		{
			if ($noaccess_display != 2)
			{
				$descr_tip  = JHtml::tooltipText($name_str, $file_data->description, 0, 1);
				$descr_icon = '<img src="components/com_flexicontent/assets/images/comments.png" class="hasTooltip" alt="'.$name_escaped.'" title="'. $descr_tip .'"/>';
				$descr_inline  = '';
			}
		}

		// As tooltip
		elseif ($display_descr==1 || $prop=='namelist')
		{
			$descr_tip  = JHtml::tooltipText($name_str, $file_data->description, 0, 1);
			$descr_icon = '<img src="components/com_flexicontent/assets/images/comments.png" class="hasTooltip" alt="'.$name_escaped.'" title="'. $descr_tip .'"/>';
			$descr_inline  = '';
		}

		// As inline text
		elseif ($display_descr==2)
		{
			$descr_inline = ' <div class="fcfile_descr_inline alert alert-info">'. nl2br($file_data->description) . '</div>';
		}

		if ($descr_icon)
		{
			$descr_icon = '
			<span class="fcfile_descr_tip">
				<span class="fcfile_descr_tip_label label">
					' .JTEXT::_('FLEXI_DESCRIPTION'). '
				</span>
				'. $descr_icon . '
			</span>
		';
		}
	}


	// ***
	// *** Create field's displayed html
	// ***

	$html = '';
	$noauth = '';


	/**
	 * Either create the download link -or- use no authorized link ...
	 */
	if (!$authorized)
	{
		$dl_link = $noaccess_url;
		if ($noaccess_msg)
		{
			$noauth = '<div class="fcfile_noauth_msg alert alert-warning">' .$noaccess_msg. '</div> ';
		}
	}
	else
	{
		$dl_link = 'index.php?option=com_flexicontent&id='. $file_id .'&cid='.$item->id.'&fid='.$field->id.'&task=download';
		$dl_link = $isAdmin ? flexicontent_html::getSefUrl($dl_link) : JRoute::_( $dl_link );
	}

	// SOME behavior FLAGS
	$not_downloadable = !$dl_link || $prop=='namelist';
	$filename_shown = (!$authorized || $show_filename);
	$filename_shown_as_link = $filename_shown && $link_filename && !$usebutton;


?>
<?php if ($prop !== 'display_properties_only') :?>
<?php

	// [0]: filename (if visible)
	if (($filename_shown && !$filename_shown_as_link) || $not_downloadable)
	{
		$html .= '<div class="fcfile_name">' . $icon . ' ' . $name_html . '</div>';
	}


	// [1]: Not authorized message
	$html .= $noauth;


	// [2]: Add information properties: filename, and icons with optional inline text
	$info_arr = array();

	if ($lang)       $info_arr[] = $lang;
	if ($sizeinfo)   $info_arr[] = $sizeinfo;
	if ($hits)       $info_arr[] = $hits;
	if ($descr_icon) $info_arr[] = $descr_icon;

	$html .= '<div class="fcclear"></div>' . implode($infoseptxt, $info_arr);


	// [3]: Add the file description (if displayed inline)
	if ($descr_inline)
	{
		$html .= '<div class="fcclear"></div>' . $descr_inline;
	}


	// [4]: Display the buttons:  DOWNLOAD, SHARE, ADD TO CART

	$actions_arr = array();


	// ***
	// *** CASE 1: no download ...
	// ***

	// EITHER (a) Current user NOT authorized to download file AND no access URL is not configured
	// OR     (b) creating a file list with no download links, (the 'prop' display variable is 'namelist')
	if ( $not_downloadable )
	{
		// nothing to do here, the file name/title will be shown above
	}


	// ***
	// *** CASE 2: Display download button passing file variables via a mini form
	// *** (NOTE: the form action can be a no access url if user is not authorized to download file)
	// ***

	else if ($usebutton)
	{
		$file_classes .= ' btn';  // ' fc_button fcsimple';   // Add an extra css class (button display)

		// DOWNLOAD: single file instant download
		if ($allowdownloads)
		{
			// NO ACCESS: add file info via URL variables, in case the URL target needs to use them
			if (!$authorized && $noaccess_addvars)
			{
				$vars = array(
					'fc_field_id="' . $field->id,
					'fc_item_id="' . $item->id,
					'fc_file_id="' . $file_id,
				);
				$dl_link .= strpos($dl_link, '?') !== false ? '&amp;' : '?';
				$dl_link .= implode('&amp;', $vars);
			}

			// The download button in a mini form ...
			// Download 
			$_download_btn_html = '
				<a href="' . $dl_link . '" class="btn-dwn-controls fcfile_downloadFile" title="'.htmlspecialchars($downloadsinfo, ENT_COMPAT, 'UTF-8').'" ' . ($non_file_url ? 'target="_blank"' : '') . '>
					<span class="icon-chevron-down large-icon"> </span>
					' . $downloadstext . '
				</a>
			';
			// Do not add it here ... we will add it inline with player
			//$actions_arr[] = $_download_btn_html;
		}

		if ($authorized && $allowview && !$file_data->url)
		{
			$actions_arr[] = '
				<a href="' . $dl_link . (strpos($dl_link, '?') !== false ? '&amp;' : '?') . 'method=view" ' . ($viewinside==2 ? 'target="_blank"' : '')
					. ' class="' . ($viewinside==0 ? 'fancybox ' : '') . $file_classes . ' btn-info fcfile_viewFile" '.($viewinside==0 ? 'data-type="iframe" ' : '')
					. ($viewinside==1 ? ' onclick="var url = jQuery(this).attr(\'href\');  fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, 0, {title:\''. $filetitle_escaped .'\'}); return false;" ' : '')
					. ' title="' . $viewinfo . '" style="line-height:1.3em;" >
					' . $viewtext . '
				</a>';
			$fancybox_needed = $viewinside == 0;
		}

		// ADD TO CART: the link will add file to download list (tree) (handled via a downloads manager module)
		if ($authorized && $allowaddtocart && !$file_data->url)
		{
			// CSS class to anchor downloads list adding function
			$addtocart_classes = $file_classes . ' fcfile_addFile';

			$attribs = ' class="'. $addtocart_classes .'"'
				. ' title="'. $addtocartinfo .'"'
				. ' data-filename="'. $filetitle_escaped .'"'
				. ' data-fieldid="'. $field->id .'"'
				. ' data-contentid="'. $item->id .'"'
				. ' data-fileid="'. $file_data->id .'"';
			$actions_arr[] =
				'<input type="button" '. $attribs .' value="'.htmlspecialchars($addtocarttext, ENT_COMPAT, 'UTF-8').'" />';
		}


		// SHARE FILE VIA EMAIL: open a popup or inline email form ...
		if ($is_public && $allowshare && !$com_mailto_found)
		{
			// skip share popup form button if com_mailto is missing
			$actions_arr[] =
				' com_mailto component not found, please disable <b>download link sharing parameter</b> in this file field';
		}
		else if ($is_public && $allowshare)
		{
			$send_form_url = 'index.php?option=com_flexicontent&tmpl=component'
				.'&task=call_extfunc&exttype=plugins&extfolder=flexicontent_fields&extname=file&extfunc=share_file_form'
				.'&file_id='.$file_id.'&content_id='.$item->id.'&field_id='.$field->id;
			$actions_arr[] =
				'<input type="button" class="'.$file_classes.' fcfile_shareFile" title="'.$shareinfo.'" data-href="'.$send_form_url.'" value="'.htmlspecialchars($sharetext, ENT_COMPAT, 'UTF-8').'" '.
					' onclick="var url = jQuery(this).attr(\'data-href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 800, 800, 0, {title:\''.htmlspecialchars($sharetext, ENT_COMPAT, 'UTF-8').'\'}); return false;" '.
				'/>';
		}
	}


	// ***
	// *** CASE 3: display a download link (with file title or filename) passing variables via the URL
	// *** (NOTE: the target link can be a no access url if user is not authorized to download file)
	// ***

	else
	{
		// DOWNLOAD: single file instant download
		if ($allowdownloads)
		{
			// NO ACCESS: add file info via URL variables, in case the URL target needs to use them
			if ( !$authorized && $noaccess_addvars)
			{
				$vars = array(
					'fc_field_id="' . $field->id,
					'fc_item_id="' . $item->id,
					'fc_file_id="' . $file_id,
				);
				$dl_link .= strpos($dl_link, '?') !== false ? '&amp;' : '?';
				$dl_link .= implode('&amp;', $vars);
			}

			// The download link, if filename/title not shown, then display a 'download' prompt text
			$actions_arr[] =
				($filename_shown && $link_filename ? $icon.' ' : '')
				.'<a href="' . $dl_link . '" class="' . $file_classes . ' fcfile_downloadFile" title="' . htmlspecialchars($downloadsinfo, ENT_COMPAT, 'UTF-8') . '" ' . ($non_file_url ? 'target="_blank"' : '') . '>'
				.($filename_shown && $link_filename ? $name_str : $downloadstext)
				.'</a>';
		}

		if ($authorized && $allowview && !$file_data->url)
		{
			$actions_arr[] = '
				<a href="' . $dl_link . (strpos($dl_link, '?') !== false ? '&amp;' : '?') . 'method=view" ' . ($viewinside==2 ? 'target="_blank"' : '')
					. ' class="' . ($viewinside==0 ? 'fancybox ' : '') . $file_classes . ' fcfile_viewFile" '.($viewinside==0 ? 'data-type="iframe" ' : '')
					. ($viewinside==1 ? ' onclick="var url = jQuery(this).attr(\'href\');  fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, 0, {title:\''. $filetitle_escaped .'\'}); return false;" ' : '')
					. ' title="' . $viewinfo . '" >
					' . $viewtext . '
				</a>';
			$fancybox_needed = $viewinside == 0;
		}

		// ADD TO CART: the link will add file to download list (tree) (handled via a downloads manager module)
		if ($authorized && $allowaddtocart && !$file_data->url)
		{
			// CSS class to anchor downloads list adding function
			$addtocart_classes = $file_classes . ' fcfile_addFile';

			$attribs  = ' class="'. $addtocart_classes .'"'
				. ' title="'. $addtocartinfo .'"'
				. ' filename="'. $filetitle_escaped .'"'
				. ' fieldid="'. $field->id .'"'
				. ' contentid="'. $item->id .'"'
				. ' fileid="'. $file_data->id .'"';
			$actions_arr[] = '
				<a href="javascript:;" '. $attribs .' >
					' . $addtocarttext . '
				</a>';
		}

		// SHARE FILE VIA EMAIL: open a popup or inline email form ...
		if ($is_public && $allowshare && !$com_mailto_found)
		{
			// skip share popup form button if com_mailto is missing
			$html .= ' com_mailto component not found, please disable <b>download link sharing parameter</b> in this file field';
		}
		else if ($is_public && $allowshare)
		{
			$send_form_url = 'index.php?option=com_flexicontent&tmpl=component'
				.'&task=call_extfunc&exttype=plugins&extfolder=flexicontent_fields&extname=file&extfunc=share_file_form'
				.'&file_id='.$file_id.'&content_id='.$item->id.'&field_id='.$field->id;
			$actions_arr[] =
				'<a href="'.$send_form_url.'" class="fcfile_shareFile" title="'.$shareinfo.'" '.
				'  onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 800, 800, 0, {title:\''.$sharetext.'\'}); return false;">'
				.$sharetext
				.'</a>';
		}
	}

	//Display the buttons "DOWNLOAD, SHARE, ADD TO CART" before or after the filename
	$html =
		($buttonsposition ? $html : '') . '
		<div class="fcfile_actions">
			' . implode($actionseptxt, $actions_arr) . '
		</div>' .
		(!$buttonsposition ? $html : '');

	$create_preview = $field->parameters->get('mm_create_preview', 1);

	if ($create_preview)
	{
		$ext         = strtolower(flexicontent_upload::getExt($file_data->filename));
		$previewname = preg_replace('/\.' . $ext . '$/i', '', basename($file_data->filename)) . '.mp3';
		$peaksname   = preg_replace('/\.' . $ext . '$/i', '', basename($file_data->filename)) . '.json';
		$previewpath = 'audio_preview/' . $previewname;
		$peakspath   = 'audio_preview/' . $peaksname;
	}
	else
	{
		$previewpath = $file_data->filename;
		$peakspath   = null;
	}

	$html .= '<div class="fcclear"></div>'
	. '
		<div id="fcview_' . $item->id . '_' . $field->name . '_' . $n . '_file-data-txt"
			data-filename="' . htmlspecialchars($previewpath, ENT_COMPAT, 'UTF-8') . '"
			data-wfpreview="' . htmlspecialchars($previewpath, ENT_COMPAT, 'UTF-8') . '"
			data-wfpeaks="' . htmlspecialchars($peakspath, ENT_COMPAT, 'UTF-8') . '"
			class="fc-wf-filedata"
		></div>
		<div id="fc_mediafile_audio_spectrum_box_' . $item->id . '_' . $FN_n . '" class="fc_mediafile_audio_spectrum_box"
			data-fc_tagid="' . $item->id . '_' . $field->name . '_' . $n . '"
			data-fc_fname="' .$field_name_js . '"
			style="display: block; margin-top: 8px; position: relative; min-height: 128px; min-width: 100%;"
		>
			<div class="progress progress-striped active" style="visibility: hidden; position: absolute; width: 70%; top: 40%; left: 15%;">
				<div class="bar" style="width: 0%;"></div>
			</div>
			<div id="fc_mediafile_audio_spectrum_' . $item->id . '_' . $FN_n . '" class="fc_mediafile_audio_spectrum"></div>
		</div>
		<div>
			<!--div id="fc_mediafile_current_time_' . $item->id . '_' . $FN_n . '" class="media_time">00:00:00</div-->
			<div id="fc_mediafile_controls_' . $item->id . '_' . $FN_n . '" class="fc_mediafile_controls">
				<a href="javascript:;" class="btn playBtn"><span class="icon-play-circle controls"></span>' . JText::_('FLEXI_FIELD_MEDIAFILE_PLAY') . '</a>
				<a href="javascript:;" class="btn pauseBtn" style="display: none;"><span class="icon-pause-circle controls"></span>' . JText::_('FLEXI_FIELD_MEDIAFILE_PAUSE') . '</a>
				<a href="javascript:;" class="btn stopBtn" style="display: none;"><span class="icon-stop-circle controls"></span>' . JText::_('FLEXI_FIELD_MEDIAFILE_STOP') . '</a>
				<a href="javascript:;" class="btn loadBtn" style="display: none;"><span class="icon-loop controls"></span>' . JText::_('FLEXI_FIELD_MEDIAFILE_LOAD') . '</a>
				' . ($allowdownloads ? $_download_btn_html : '') . '
			</div>
		</div>
		';


endif;   // END OF   $prop !== 'display_properties_only'


	/**
	 * Basic display of audio / video media data
	 */
	if (isset($file_data->media_type) && $prop === 'display_properties_only' && $view === 'item')
	{
		/*
		$mediadata = array(
			//'state', 'media_type', 'codec_type', 'codec_name', 'codec_long_name', 'resolution', 'fps',
			'media_format', 'bit_rate', 'bits_per_sample', 'sample_rate', 'duration',
			'channels', 'channel_layout', 'checked_out', 'checked_out_time');
		*/
		$mediadata = array('media_format','bit_rate', 'bits_per_sample', 'sample_rate', 'duration', 'channels');

		$html .= '<table class="table audiotable">';
		foreach($mediadata as $md_name)
		{
			$PROP_NAME = $md_name;
			$PROP_VALUE = $file_data->$md_name;
			
				if ($md_name == 'channels')
			{
				// Change 'channels' to '# Channels' you can also you language   JText::_('SOMENAME');
				$PROP_NAME = '<span class="icon-play-circle large-icon"> </span> Channels';
				
				// Only change value if it is 2 or 1
				if ($PROP_VALUE == 2 || $PROP_VALUE == 1)
				{
					$PROP_VALUE = $PROP_VALUE == 2 ? 'Stereo' : 'Mono';
				}
			
			}
			
				if ($md_name == 'media_format')
			{
				// Change 'channels' to '# Channels' you can also you language   JText::_('SOMENAME');
				$PROP_NAME = '<span class="icon-music large-icon"> </span> Media Type';	
			}
			
				if ($md_name == 'bit_rate')
			{
				// Change 'channels' to '# Channels' you can also you language   JText::_('SOMENAME');
				$PROP_NAME = '<span class="icon-options large-icon"> </span> Bitrate';
				$PROP_VALUE = ($PROP_VALUE / 1000).' Kbps';
			}
				if ($md_name == 'bits_per_sample')
			{
				// Change 'channels' to '# Channels' you can also you language   JText::_('SOMENAME');
				$PROP_NAME = '<span class="icon-options large-icon"> </span> Bit depth';
				$PROP_VALUE = $PROP_VALUE.' Bit';	
			}
				if ($md_name == 'sample_rate')
			{
				// Change 'channels' to '# Channels' you can also you language   JText::_('SOMENAME');
				$PROP_NAME = '<span class="icon-health large-icon"> </span> Sample Rate';	
				$PROP_VALUE = $PROP_VALUE.' Hz';
			}
				if ($md_name == 'duration')
			{
				// Change 'channels' to '# Channels' you can also you language   JText::_('SOMENAME');
				$PROP_NAME = '<span class="icon-clock large-icon"> </span> Duration';
				$PROP_VALUE = gmdate("H:i:s", $PROP_VALUE);	
			}
				if ($PROP_VALUE == "wav") {
					$media_format = "wav";
				}
				if ($PROP_VALUE == "aiff") {
					$media_format = "aiff";
				}
				if ($PROP_VALUE == "mp3") {
					$media_format = "mp3";
				}
						
			if ($md_name == 'bit_rate' && ($media_format == 'wav' || $media_format == 'aiff'))  continue;
			if ($md_name == 'bits_per_sample' && $media_format == 'mp3')  continue;
			
			
			$html .=  '
				<tr>
					<td class="key"> ' . $PROP_NAME . '</td>
					<td>' . $PROP_VALUE . '</td>
				</tr>';
		}
		$html .= '</table>';
	}


	// Values Prefix and Suffix Texts
	$field->{$prop}[$n]	=  $pretext . $html . $posttext;

	// Some extra data for developers: (absolute) file URL and (absolute) file path
	$field->url[$use_ingroup ? $n : $i] = $dl_link;
	$field->abspath[$use_ingroup ? $n : $i] = $abspath;
	$field->file_data[$use_ingroup ? $n : $i] = $file_data;

	/*if ($filename_original && $prop !== 'display_properties_only')
	{
		$per_value_js .= "
			fcview_mediafile.initValue('" . $item->id . '_' . $field->name . '_' . $n . "', '".$field_name_js."');
		";
	}*/

	// Add microdata to every value if field -- is -- in a field group
	if ($is_ingroup && $itemprop) $field->{$prop}[$n] = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}[$n]. '</div>';

	$n++;
	$i++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}

JFactory::getDocument()->addScriptDeclaration("
	fcview_mediafile_base_url['".$field_name_js."'] = '".$base_url."';
	
	" . (!$per_value_js ? "" : "
	//document.addEventListener('DOMContentLoaded', function()
	jQuery(document).ready(function()
	{
		" . $per_value_js . "
	});
"));


// ***
// *** Create total INFO
// ***

$file_totals = '';

// Total number of files
if ($display_total_count && $prop !== 'display_properties_only')
{
	$file_totals .= '
		<div class="fcfile_total_count">
			<span class="fcfile_total_count_label">'. $total_count_label .' </span>
			<span class="fcfile_total_count_value badge">'. count($values) .'</span>
		</div>
	';
}

// Total download hits (of all files)
if ($display_total_hits && $field->hits_total && $prop !== 'display_properties_only')
{
	$file_totals .='
		<div class="fcfile_total_hits">
			<span class="fcfile_total_hits_label">'. $total_hits_label .' </span>
			<span class="fcfile_total_hits_value badge">'. $field->hits_total .'</span>
		</div>
	';
}

// Add -1 position (display at top of field or at top of field, or at top/bottom of field group)
if ($file_totals && $prop !== 'display_properties_only')
{
	$field->{$prop}[-1] = '
		<div class="alert alert-success fcfile_total">
			' . $file_totals . '
		</div>
		';
}
