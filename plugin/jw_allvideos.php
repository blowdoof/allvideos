<?php
/**
 * @version		4.6.1
 * @package		AllVideos (plugin)
 * @author    	JoomlaWorks - http://www.joomlaworks.net
 * @copyright	Copyright (c) 2006 - 2014 JoomlaWorks Ltd. All rights reserved.
 * @license		GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');
if (version_compare(JVERSION, '1.6.0', 'ge')) {
	jimport('joomla.html.parameter');
}

class plgContentJw_allvideos extends JPlugin {

	// JoomlaWorks reference parameters
	var $plg_name					= "jw_allvideos";
	var $plg_copyrights_start		= "\n\n<!-- JoomlaWorks \"AllVideos\" Plugin (v4.6.1) starts here -->\n";
	var $plg_copyrights_end			= "\n<!-- JoomlaWorks \"AllVideos\" Plugin (v4.6.1) ends here -->\n\n";

	function plgContentJw_allvideos( &$subject, $params ) {
		parent::__construct( $subject, $params );

		// Define the DS constant under Joomla! 3.0+
		if (!defined('DS')) {
			define('DS', DIRECTORY_SEPARATOR);
		}
	}

	// Joomla! 1.5
	function onPrepareContent(&$row, &$params, $page = 0){
		$this->renderAllVideos($row, $params, $page = 0);
	}

	// Joomla! 2.5+
	function onContentPrepare($context, &$row, &$params, $page = 0){
		$this->renderAllVideos($row, $params, $page = 0);
	}

	// The main function
	function renderAllVideos(&$row, &$params, $page = 0) {

		// API
		jimport('joomla.filesystem.file');
		$mainframe = JFactory::getApplication();
		$document  = JFactory::getDocument();

		// Assign paths
		$sitePath = JPATH_SITE;
		$siteUrl  = JURI::root(true);
		if (version_compare(JVERSION, '1.6.0', 'ge')) {
			$pluginLivePath = $siteUrl.'/plugins/content/'.$this->plg_name.'/'.$this->plg_name;
		} else {
			$pluginLivePath = $siteUrl.'/plugins/content/'.$this->plg_name;
		}

		// Check if plugin is enabled
		if (JPluginHelper::isEnabled('content',$this->plg_name)==false) return;

		// Load the plugin language file the proper way
		JPlugin::loadLanguage('plg_content_'.$this->plg_name, JPATH_ADMINISTRATOR);

		// Includes
		require_once(dirname(__FILE__).DS.$this->plg_name.DS.'includes'.DS.'helper.php');
		require(dirname(__FILE__).DS.$this->plg_name.DS.'includes'.DS.'sources.php');

		// Simple performance check to determine whether plugin should process further
		$grabTags = strtolower(implode(array_keys($tagReplace),"|"));
		if (preg_match("#{(".$grabTags.")}#is", $row->text)==false) return;



		// ----------------------------------- Get plugin parameters -----------------------------------

		// Get plugin info
		$plugin = JPluginHelper::getPlugin('content', $this->plg_name);

		// Control external parameters and set variable for controlling plugin layout within modules
		if (!$params) {
			$params = class_exists('JParameter') ? new JParameter(null) : new JRegistry(null);
		}
		$parsedInModule = $params->get('parsedInModule');

		$pluginParams = class_exists('JParameter') ? new JParameter($plugin->params) : new JRegistry($plugin->params);

		/* Video Parameters */
		$playerTemplate					= ($params->get('playerTemplate')) ? $params->get('playerTemplate') : $pluginParams->get('playerTemplate','Classic');
		$vfolder 						= ($params->get('vfolder')) ? $params->get('vfolder') : $pluginParams->get('vfolder','images/stories/videos');
		$vwidth 						= ($params->get('vwidth')) ? $params->get('vwidth') : $pluginParams->get('vwidth',400);
		$vheight 						= ($params->get('vheight')) ? $params->get('vheight') : $pluginParams->get('vheight',300);
		$transparency 					= $pluginParams->get('transparency','transparent');
		$background 					= $pluginParams->get('background','#010101');
		$backgroundQT					= $pluginParams->get('backgroundQT','black');
		$controls 						= $pluginParams->get('controls',1);
		/* Audio Parameters */
		$afolder 						= $pluginParams->get('afolder','images/stories/audio');
		$awidth 						= ($params->get('awidth')) ? $params->get('awidth') : $pluginParams->get('awidth',480);
		$aheight 						= ($params->get('aheight')) ? $params->get('aheight') : $pluginParams->get('aheight',24);
		$abackground 					= $pluginParams->get('abackground','#010101');
		$afrontcolor 					= $pluginParams->get('afrontcolor','#FFFFFF');
		$alightcolor 					= $pluginParams->get('alightcolor','#00ADE3');
		$allowAudioDownloading			= $pluginParams->get('allowAudioDownloading',0);
		/* Global Parameters */
		$autoplay 						= ($params->get('autoplay')) ? $params->get('autoplay') : $pluginParams->get('autoplay',0);
		/* Performance Parameters */
		$gzipScripts					= $pluginParams->get('gzipScripts',0);
		/* Advanced */
		$jwPlayerLoading				= $pluginParams->get('jwPlayerLoading','local'); // local | cdn
		$jwPlayerAPIKey					= $pluginParams->get('jwPlayerAPIKey','plXkZcoHeQXVlRo0nD6AUscwEXmFJCmIpGL3kw==');
		$jwPlayerCDNUrl					= $pluginParams->get('jwPlayerCDNUrl','http://jwpsrv.com/library/n9Po9gncEeOKaBIxOUCPzg.js');

		// Variable cleanups for K2
		if (JRequest::getCmd('format')=='raw') {
			$this->plg_copyrights_start = '';
			$this->plg_copyrights_end = '';
		}

		// Assign the AllVideos helper class
		$AllVideosHelper = new AllVideosHelper;



		// ----------------------------------- Render the output -----------------------------------

		// Append head includes only when the document is in HTML mode
		if (JRequest::getCmd('format')=='html' || JRequest::getCmd('format')=='') {

			// CSS
			$avCSS = $AllVideosHelper->getTemplatePath($this->plg_name,'css/template.css',$playerTemplate);
			$avCSS = $avCSS->http;
			$document->addStyleSheet($avCSS);

			// JS
			if (version_compare(JVERSION,'1.6.0','ge')) {
				JHtml::_('behavior.framework');
			} else {
				JHTML::_('behavior.mootools');
			}

			if ($gzipScripts) {
				$document->addScript($pluginLivePath.'/includes/js/jwp.js.php?v=4.6.1');
			} else {
				$document->addScript($pluginLivePath.'/includes/js/behaviour.js?v=4.6.1');
				$document->addScript($pluginLivePath.'/includes/js/wmvplayer/silverlight.js?v=4.6.1');
				$document->addScript($pluginLivePath.'/includes/js/wmvplayer/wmvplayer.js?v=4.6.1');
				$document->addScript($pluginLivePath.'/includes/js/quicktimeplayer/AC_QuickTime.js?v=4.6.1');
			}

			if($jwPlayerLoading=='local'){
				$document->addScript($pluginLivePath.'/includes/js/jwplayer/jwplayer.js?v=4.6.1');
				$document->addScriptDeclaration('
					/* JW Player API Key */
					jwplayer.key="'.$jwPlayerAPIKey.'";
				');
			} else {
				$document->addScript($jwPlayerCDNUrl);
			}

		}

		// Loop throught the found tags
		$tagReplace = array_change_key_case($tagReplace, CASE_LOWER);
		foreach ($tagReplace as $plg_tag => $value) {

			$cloned_plg_tag = $plg_tag;
			$plg_tag = strtolower($plg_tag);

			// expression to search for
			$regex = "#{".$plg_tag."}.*?{/".$plg_tag."}#is";

			// process tags
			if (preg_match_all($regex, $row->text, $matches, PREG_PATTERN_ORDER)) {

				// start the replace loop
				foreach ($matches[0] as $key => $match) {

					$tagcontent 		= preg_replace("/{.+?}/", "", $match);
					$tagparams 			= explode('|',$tagcontent);
					$tagsource 			= trim(strip_tags($tagparams[0]));

					// Prepare the HTML
					$output = new JObject;

					// Width/height/source folder split per media type
					if (in_array($plg_tag, array(
						'mp3',
						'mp3remote',
						'aac',
						'aacremote',
						'm4a',
						'm4aremote',
						'ogg',
						'oggremote',
						'wma',
						'wmaremote',
						'soundcloud'
					))) {
						$final_awidth 	= (@$tagparams[1]) ? $tagparams[1] : $awidth;
						$final_aheight 	= (@$tagparams[2]) ? $tagparams[2] : $aheight;

						$output->playerWidth = $final_awidth;
						$output->playerHeight = $final_aheight;
						$output->folder = $afolder;

						if ($plg_tag=='soundcloud') {
							if (strpos($tagsource,'/sets/')!==false) {
								$output->mediaTypeClass = ' avSoundCloudSet';
							} else {
								$output->mediaTypeClass = ' avSoundCloudSong';
							}
							$output->mediaType = '';
						} else {
							$output->mediaTypeClass = ' avAudio';
							$output->mediaType = 'audio';
						}

						if (in_array($plg_tag, array('mp3','aac','m4a','ogg','wma'))) {
							$output->source = "$siteUrl/$afolder/$tagsource.$plg_tag";
						} elseif (in_array($plg_tag, array('mp3remote','aacremote','m4aremote','oggremote','wmaremote'))) {
							$output->source = $tagsource;
						} else {
							$output->source = '';
						}
					} else {
						$final_vwidth 	= (@$tagparams[1]) ? $tagparams[1] : $vwidth;
						$final_vheight 	= (@$tagparams[2]) ? $tagparams[2] : $vheight;

						$output->playerWidth = $final_vwidth;
						$output->playerHeight = $final_vheight;
						$output->folder = $vfolder;
						$output->mediaType = 'video';
						$output->mediaTypeClass = ' avVideo';
					}

					// Autoplay
					$final_autoplay = (@$tagparams[3]) ? $tagparams[3] : $autoplay;
					$final_autoplay	= ($final_autoplay) ? 'true' : 'false';

					// Special treatment for specific video providers
					if ($plg_tag=="dailymotion") {
						$tagsource = preg_replace("~(http|https):(.+?)dailymotion.com\/video\/~s","",$tagsource);
						$tagsourceDailymotion = explode('_',$tagsource);
						$tagsource = $tagsourceDailymotion[0];
						if ($final_autoplay=='true') {
							if (strpos($tagsource,'?')!==false) {
								$tagsource = $tagsource.'&amp;autoPlay=1';
							} else {
								$tagsource = $tagsource.'?autoPlay=1';
							}
						}
					}

					if ($plg_tag=="ku6") {
						$tagsource = str_replace('.html','',$tagsource);
					}

					if ($plg_tag=="metacafe" && substr($tagsource,-1,1)=='/') {
						$tagsource = substr($tagsource,0,-1);
					}

					if ($plg_tag=="sevenload") {
						$tagsource = parse_url($tagsource);
						$tagsource = explode('-',$tagsource['query']);
						$tagsource = array_reverse($tagsource);
						$tagsource = $tagsource[0];
					}

					if ($plg_tag=="sohu") {
						$tagsource = parse_url($tagsource);
						$tagsource = explode('/',$tagsource['query']);
						$tagsource = array_reverse($tagsource);
						$tagsource = str_replace('.shtml','',$tagsource[0]);
					}

					if ($plg_tag=="tnaondemand") {
						$tagsource = parse_url($tagsource);
						$tagsource = explode('&',$tagsource['query']);
						$tagsource = str_replace('vidid=','',$tagsource[0]);
					}

					if ($plg_tag=="twitvid") {
						$tagsource = preg_replace("~(http|https):(.+?)twitvid.com\/~s","",$tagsource);
						if ($final_autoplay=='true') {
							$tagsource = $tagsource.'&amp;autoplay=1';
						}
					}

					if ($plg_tag=="vidiac") {
						$tagsourceVidiac = explode(';',$tagsource);
						$tagsource = $tagsourceVidiac[0];
					}

					if ($plg_tag=="vimeo") {
						$tagsource = preg_replace("~(http|https):(.+?)vimeo.com\/~s","",$tagsource);
						if (strpos($tagsource,'?')!==false) {
							$tagsource = $tagsource.'&amp;portrait=0';
						} else {
							$tagsource = $tagsource.'?portrait=0';
						}
						if ($final_autoplay=='true') {
							$tagsource = $tagsource.'&amp;autoplay=1';
						}
					}

					if ($plg_tag=="yahoo") {
						$tagsourceYahoo = explode('-',str_replace('.html','',$tagsource));
						$tagsourceYahoo = array_reverse($tagsourceYahoo);
						$tagsource = $tagsourceYahoo[0];
					}

					if ($plg_tag=="yfrog") {
						$tagsource = preg_replace("~(http|https):(.+?)yfrog.com\/~s","",$tagsource);
					}

					if ($plg_tag=="youmaker") {
						$tagsourceYoumaker = explode('-',str_replace('.html','',$tagsource));
						$tagsource = $tagsourceYoumaker[1];
					}

					if ($plg_tag=="youku") {
						$tagsource = str_replace('.html','',$tagsource);
						$tagsource = substr($tagsource,3);
					}

					if ($plg_tag=="youtube") {
						if (strpos($tagsource,'youtube.com')!==false) {
							$tagsource = preg_replace("~(http|https):(.+?)youtube.com\/watch\?~s","",$tagsource);
						}
						if (strpos($tagsource,'youtu.be')!==false) {
							$tagsource = preg_replace("~(http|https):(.+?)youtu.be\/~s","",$tagsource);
						}
						if (strpos($tagsource,'&')!==false) {
							$tagsourceYoutube = explode('&',$tagsource);
							foreach($tagsourceYoutube as $ytVideoParam) {
								if (preg_match("~v=~s", $ytVideoParam)) {
									$tagsource = str_replace("v=", "", $ytVideoParam);
								}
							}
						} else {
							if (strpos($tagsource,'=')!==false) {
								$tagsourceYoutube = explode('=',$tagsource);
								$tagsource = $tagsourceYoutube[1];
							}
						}

						$tagsource = $tagsource.'?rel=0&amp;fs=1&amp;wmode=transparent';

						if ($final_autoplay=='true') {
							$tagsource = $tagsource.'&amp;autoplay=1';
						}
					}

					// Poster frame
					$posterFramePath = $sitePath.DS.str_replace('/',DS,$vfolder);
					if (JFile::exists($posterFramePath.DS.$tagsource.'.jpg')) {
						$output->posterFrame = $siteUrl.'/'.$vfolder.'/'.$tagsource.'.jpg';
					} elseif (JFile::exists($posterFramePath.DS.$tagsource.'.png')) {
						$output->posterFrame = $siteUrl.'/'.$vfolder.'/'.$tagsource.'.png';
					} elseif (JFile::exists($posterFramePath.DS.$tagsource.'.gif')) {
						$output->posterFrame = $siteUrl.'/'.$vfolder.'/'.$tagsource.'.gif';
					} else {
						$output->posterFrame = '';
					}

					// Poster frame (remote)
					$output->posterFrameRemote = substr($tagsource,0,-3).'jpg';

					// Set a unique ID
					$output->playerID = 'AVPlayerID_'.substr(md5($tagsource),1,8).'_'.rand();

                                        // Subtitle?
					$subtitle_vars = (@$tagparams[4]) ? $tagparams[4] : 'false';
                                        $subs = array();
                                        $substring = '[]';
                                        if ($subtitle_vars) {
                                            $substring = '[';
                                            $subs = explode(';',$subtitle_vars);                                           
                                            foreach ($subs as &$sub) {
                                                // search for the 'file' part
                                                $parts = explode(',', $sub);
                                                foreach ($parts as &$part) {
                                                    $part = trim($part);
                                                    if (strtolower(substr($part, 0, 5)) === 'file:') {
                                                        //replace the file with the relative file location
                                                        $fileparts = explode(':', $part);
                                                        if (isset($fileparts[1])) {
                                                            $urlpart = trim($fileparts[1]);
                                                            // if the filepath already contains a '/', assume it is set as a relative path, else create the relative path to the movie folder
                                                            if (strpos($fileparts[1], chr(47)) === false) { 
                                                                //remove the " from the urlpart
                                                                $urlpart = str_replace('"', '', $urlpart);
                                                                $urlpart = '"' . $siteUrl . '/' . $output->folder .'/' . $urlpart . '"';
                                                            } 
                                                            $part = 'file: ' . $urlpart;
                                                        }                                                        
                                                    }
                                                }
                                                // add the default 'kind' option
                                                $parts[] = 'kind: "captions"';
                                                $sub = implode(', ', $parts);
                                                $sub = '{' . $sub . '}';                                                
                                            }
                                            $substring .= implode(',', $subs);
                                            $substring .= ']';
                                        }
          
					// Placeholder elements
					$findAVparams = array(
						"{SOURCE}",
						"{SOURCEID}",
						"{FOLDER}",
						"{WIDTH}",
						"{HEIGHT}",
						"{PLAYER_AUTOPLAY}",
						"{PLAYER_TRANSPARENCY}",
						"{PLAYER_BACKGROUND}",
						"{PLAYER_BACKGROUNDQT}",
						"{JWPLAYER_CONTROLS}",
						"{SITEURL}",
						"{SITEURL_ABS}",
						"{FILE_EXT}",
						"{PLUGIN_PATH}",
						"{PLAYER_POSTER_FRAME}",
						"{PLAYER_POSTER_FRAME_REMOTE}",
						"{PLAYER_ABACKGROUND}",
						"{PLAYER_AFRONTCOLOR}",
						"{PLAYER_ALIGHTCOLOR}",
                                                "{SUBTITLES}"
					);

					// Replacement elements
					$replaceAVparams = array(
						$tagsource,
						$output->playerID,
						$output->folder,
						$output->playerWidth,
						$output->playerHeight,
						$final_autoplay,
						$transparency,
						$background,
						$backgroundQT,
						$controls,
						$siteUrl,
						substr(JURI::root(false),0,-1),
						$plg_tag,
						$pluginLivePath,
						$output->posterFrame,
						$output->posterFrameRemote,
						$abackground,
						$afrontcolor,
						$alightcolor,
                                                $substring
					);

					// Do the element replace
					$output->player = JFilterOutput::ampReplace(str_replace($findAVparams, $replaceAVparams, $tagReplace[$cloned_plg_tag]));

					// Fetch the template
					ob_start();
					$getTemplatePath = $AllVideosHelper->getTemplatePath($this->plg_name,'default.php',$playerTemplate);
					$getTemplatePath = $getTemplatePath->file;
					include($getTemplatePath);
					$getTemplate = $this->plg_copyrights_start.ob_get_contents().$this->plg_copyrights_end;
					ob_end_clean();

					// Output
					$row->text = preg_replace("#{".$plg_tag."}".preg_quote($tagcontent)."{/".$plg_tag."}#is", $getTemplate , $row->text);

				} // End second foreach

			} // End if

		} // End first foreach

	} // End function

} // End class
