<?php
/**
* @version		2.7.8
* @author		Michael A. Gilkes (jaido7@yahoo.com)
* @copyright	Michael Albert Gilkes
* @license		GNU/GPLv2
*/

/*

Easy File Uploader Module for Joomla!
Copyright (C) 2010-2016  Michael Albert Gilkes

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/

//no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

//get the module class designation
$moduleclass_sfx = htmlspecialchars($params->get('moduleclass_sfx'));

//set up the custom text
$labelText = $params->get('efu_label');
$buttonText = $params->get('efu_button');
$questionText = $params->get('efu_question');
$yesText = $params->get('efu_yes');
$noText = $params->get('efu_no');
if ($params->get('efu_custom') == 0)
{
	$labelText = JText::_('MOD_EFU_LABEL_TEXT');
	$buttonText = JText::_('MOD_EFU_BUTTON_TEXT');
	$questionText = JText::_('MOD_EFU_QUESTION_TEXT');
	$yesText = JText::_('JYES');
	$noText = JText::_('JNO');
}

//specify the action
$action = JUri::current();

?>
<div class="<?php echo $moduleclass_sfx;?>">
	<!-- Display the Results of the file upload if uploading was attempted -->
	<?php if (isset($_FILES[$params->get('efu_variable')])) : ?>
		<?php for ($j = 0; $j < count($result); $j++) : ?>
			<?php 
			/**
			* Just a quick note here. I decided to add a class, efum-hide, which contains
			* a single block, display=none;. I could have just added the style directly or 
			* not display the div code at all. I just think adding the class looks cleaner. 
			* The only problem can be browser caching of the page, that may not refresh the 
			* styles.css file where the class is defined.
			*/
			$show_class = "";
			if ($result[$j]['show'] == false) : 
				$show_class = " efum-hide";
			endif; ?>
			<div class="efum-alert efum-<?php echo $result[$j]['type'].$show_class;?>">
			<span class="close-btn" onclick="this.parentNode.style.display = 'none';">&times;</span>
			<?php echo $result[$j]['text']; ?>
			</div>
		<?php endfor; ?>
	<?php endif; ?>
	<!-- Input form for the File Upload -->
	<form enctype="multipart/form-data" action="<?php echo $action; ?>" method="post">
		<?php if ($params->get('efu_multiple') == "1"): ?>
		<label for=<?php echo '"'.$params->get('efu_variable').'[]"'; ?>><?php echo $labelText; ?></label>
		<?php else: ?>
		<?php echo $labelText; ?><br />
		<?php endif; ?>
		<?php 
		$max = intval($params->get('efu_multiple'));
		for ($i = 0; $i < $max; $i++): ?>
		<input type="file" name=<?php echo '"'.$params->get('efu_variable').'[]"'; ?> id=<?php echo '"'.$params->get('efu_variable').'[]"'; ?> style="margin-top:1px; margin-bottom:1px;" /> 
		<br />
		<?php endfor; ?>
		<?php if ($params->get('efu_replace') == true): /* 1 means 'Yes' or true. 0 means 'No' or false. */ ?>
		<div><?php echo $questionText; ?></div>
		<input type="radio" name="answer" value="1" /><?php echo $yesText; ?><br />
		<input type="radio" name="answer" value="0" checked /><?php echo $noText; ?><br />
		<br />
		<?php endif; ?>
		<input class="btn" type="submit" name="submit" value=<?php echo '"'.$buttonText.'"'; ?> />
	</form>
</div>