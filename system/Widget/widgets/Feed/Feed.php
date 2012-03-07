<?php

class Feed extends WidgetBase {
	function WidgetLoad()
	{
    	$this->addcss('feed.css');
    	$this->addjs('feed.js');
		$this->registerEvent('post', 'onPost');
		$this->registerEvent('streamreceived', 'onStream');
    }
    
    function onPost($payload) {
        $query = Message::query()
                            ->where(array('key' => $this->user->getLogin(), 'nodeid' => $payload['event']['items']['item']['@attributes']['id']));
        $post = Message::run_query($query);

        if($post != false) {  
            $html = preparePost($post[0]);
            RPC::call('movim_prepend', 'feedcontent', RPC::cdata($html));
        }
    }
    
    function prepareFeed($start) {
        $query = Message::query()
                            ->where(array('key' => $this->user->getLogin(), 'parentid' => ''))
                            ->orderby('updated', true)
                            ->limit($start, '20');
        $messages = Message::run_query($query);
		
		if($messages == false) {
			$html = '
				<script type="text/javascript">
					setTimeout(\''.$this->genCallAjax('ajaxFeed').'\', 500);
				</script>';
			echo t('Loading your feed ...');
		} else {
			$html = '';
			
			foreach($messages as $message) {
				$html .= preparePost($message);
			}
			
            $next = $start + 20;
            
			if(sizeof($messages) > 0)
				$html .= '<div class="post older" onclick="'.$this->genCallAjax('ajaxGetFeed', "'".$next."'").'; this.style.display = \'none\'">'.t('Get older posts').'</div>';
		}
		
		return $html;
	}
	
	function ajaxGetFeed($start) {
		RPC::call('movim_append', 'feedcontent', RPC::cdata($this->prepareFeed($start)));
        RPC::commit();
	}
    
    function onStream($payload) {
        $html = '';
        $i = 0;
        
        $query = Contact::query()
                            ->where(array('key' => $user->getLogin(), 'jid' => $this->user->getLogin()));
        $contact = Contact::run_query($query);
        
        if(isset($contact[0]))
            $photo = $contact[0]->getPhoto();
        
        if(isset($payload['pubsub']['items']['item'][0]['@attributes'])) {
            foreach($payload['pubsub']['items']['item'] as $post) {
                $html .= '
                <div class="post" id="'.$post['@attributes']['id'].'">
			        <img class="avatar" src="'.$photo.'">

     			        <span><a href="?q=friend&f='.$this->user->getLogin().'">'.t('Me').'</a></span>
     			        <span class="date">'.prepareDate(strtotime($post['entry']['published'])).'</span>
     			    <div class="content"> 
     			    '.prepareString($post['entry']['content']).'
	            	</div>
	            	<!--<div class="comments" id="'.$post['@attributes']['id'].'comments">
	            	    <a onclick="'.$this->genCallAjax('ajaxGetComments', "'".$_GET['f']."'", "'".$post['@attributes']['id']."'").'">'.t('Get the comments').'</a>
	            	</div>-->
           		</div>';
            }
        }
        
        if($html == '') 
            $html = t("Your feed cannot be loaded.");
        RPC::call('movim_fill', 'feed_content', RPC::cdata($html));
    }
    
    function ajaxPublishItem($content)
    {
        $this->xmpp->publishItem($content);
    }
    
    function ajaxCreateNode()
    {
        global $sdb;
        $conf = new ConfVar();
        $sdb->load($conf, array(
                            'login' => $this->user->getLogin()
                                ));
        $conf->setConf(false, false, false, false, false, false, false, false, false, true);
        $sdb->save($conf);
        
        $this->xmpp->createNode();
        
        RPC::call('movim_reload');
        RPC::commit();
    }
    
    function ajaxFeed()
    {
        $this->xmpp->getWall($this->xmpp->getCleanJid());
    }
    
    function build()
    {
    ?>
    <div class="tabelem protect orange" title="<?php echo t('Feed'); ?>" id="feed">
		<table id="submit">
			<tr>
				<td id="feedmessage">
					<input 
						id="feedmessagecontent"
						class="big" 
						onfocus="this.value=''; this.style.color='#333333'; this.onfocus=null;" 
						value="<?php echo t('What\'s new ?'); ?>">
				</td>
				<td>
					<a 
						title="<?php echo t("Submit"); ?>"
						onclick="<?php $this->callAjax('ajaxPublishItem', "document.querySelector('#feedmessagecontent').value") ?>"
						href="#" 
						id="feedmessagesubmit" 
						class="button tiny icon submit">
					</a>
				</td>
			</tr>
		</table>

        <!--<a href="#"  onclick="<?php $this->callAjax('ajaxPublishItem', "'BAZINGA !'") ?>">go !</a>-->
        <!--<a href="#"  onclick="<?php $this->callAjax('ajaxCreateNode') ?>">create !</a>-->
        <!--<a href="#"  onclick="<?php $this->callAjax('ajaxGetElements') ?>">get !</a>-->
        <div id="feedfilters">
			<ul>
				<li class="on" onclick="showPosts(this, false);"><?php echo t('All');?></li>
				<li onclick="showPosts(this, true);"><?php echo t('My Posts');?></li>
			</ul>
        </div>
        
        <div id="feedcontent">
            <?php
            
            $query = ConfVar::query()
                                ->where(array('login' => $this->user->getLogin()));
            $conf = ConfVar::run_query($query);

            $conf_arr = $conf[0]->getConf(); 
            if($conf_arr["first"] == 0) { 
            ?>
                    <a 
                    onclick="<?php $this->callAjax('ajaxCreateNode') ?>"
                    href="#" class="button tiny icon add">&nbsp;&nbsp;<?php echo t("Create the feed"); ?></a><br />
            <?php
            }
            
            echo $this->prepareFeed(0);
            
            ?>
        </div>
    </div>
    <?php
    }
}
