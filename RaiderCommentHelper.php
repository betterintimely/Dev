<?php
namespace BAE\Helper\Raider;
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
use BAE\Model\Zone\RaiderCommentModel;
use BAE\Model\Zone\RaiderModel;
use BAE\Helper\ContentHelper;
use BAE\Entity\Zone\RaiderComment;
use BAE\Helper\ArrayHelper;
use BAE\Model\User\UserInfoModel;
use BAE\Entity\Shop\UserMessage;
class RaiderCommentHelper {
	public static function addComment($raiderId,$content, $uid, $uname, $replyId = 0){
		//检测攻略是否存在
		$raiderInfo = RaiderModel::isExists($raiderId);
		if(!$raiderInfo){
			return ['msg' => '评论的攻略不存在', 'flag' => false];
		}
		//如果回复id存在 检测信息是否存在

		if($replyId){
			$parentRaiderComment = RaiderCommentModel::getComment($replyId, $raiderId);
			if(!$parentRaiderComment){
				return ['msg' => '回复的评论不存在', 'flag' => false];
			}
		}
		
		$isAllow = $isAllow = ContentHelper::isDenyContent($content);
		$RC = new RaiderComment();
		$RC->raider_id = $raiderId;
		$RC->status = $isAllow ? RaiderComment::STATUS_AUTO_CHECKED : RaiderComment::STATUS_PENDDING_CHECK;
		$RC->content = $content;
		$RC->create_time = date('Y-m-d H:i:s');
		$RC->uid = $uid;
		$RC->uname = $uname;
		if($replyId){
			$RC->reply_uid = $parentRaiderComment['uid'];
			$RC->reply_uname = $parentRaiderComment['uname'];
			$RC->reply_id  = $replyId;

		}
		
		$ret = $RC->save();
		if(!$ret){
			return ['flag' => false, 'msg' => '保存失败'];
		}
		//给用户发消息
		$sendUserInfo = UserInfoModel::getUserInfo($uid);
		if($sendUserInfo){
			if($replyId){
				$type = UserMessage::RAIDER_REPLY;
				$title = '用户'.$sendUserInfo['nick_name'].'回复了';
				$receiveUid = $parentRaiderComment['uid'];
			}else{
				$type = UserMessage::RAIDER_COMMENT;
				$title = '用户'.$sendUserInfo['nick_name'].'评论了你的攻略';
				$receiveUid = $raiderInfo['nick_user_id'];
			}
			$UserMessage = new UserMessage();
			$UserMessage->origin_id = $raiderId;
			$UserMessage->origin_type = $type;
			$UserMessage->uid = $receiveUid;
			$UserMessage->title = $title;
			$UserMessage->ext =	$uid;
			$UserMessage->content = urlencode($content);
			$UserMessage->addtime = date("Y-m-d H:i:s");
			$UserMessage->save();
		}
		return ['flag' => true, 'msg' => '保存成功'];
	}
	
	public static function getRaiderCommentList($raiderId, $page = 1, $take = 5){
		$RC = RaiderCommentModel::getRaiderCommentObject();
		$commentList = $RC->where('reply_id', 0)
					->where('raider_id', $raiderId)
					->take($take)
					->skip(($page-1) * $take)
					->orderBy('id', 'desc')
					->get()
					->toArray();
		if(!$commentList){
			return [];
		}
		
		$commentList = self::formatCommentList($commentList);
		foreach ($commentList as &$item){
			$RC = RaiderCommentModel::getRaiderCommentObject();
			$subCommentList = $RC->where('reply_id', '!=', 0)
					->where('raider_id', $raiderId)
					->take(100)
					->orderBy('id', 'asc')
					->get()
					->toArray();
			if(!$subCommentList){
				continue;
			}
			
			$item['subComment'] = self::formatCommentList($subCommentList);
		}
		
		return $commentList;
	}

	public static function formatCommentList($commentList){
		if(!$commentList){
			return [];
		}
		
		//取用户信息
		$commentUids = ArrayHelper::arrayKeyValues($commentList, 'uid');
		$parentUids = ArrayHelper::arrayKeyValues($commentList, 'reply_uid');
		$uids = array_unique($commentUids + $parentUids);
		$userInfo = UserInfoModel::getUserInfo($uids);
		foreach($commentList as &$item){
			$item['masterUser'] = isset($userInfo[$item['uid']]) ? $userInfo[$item['uid']] : [];
			$item['parentUser'] = isset($userInfo[$item['reply_uid']]) ? $userInfo[$item['reply_uid']] : [];
			$item['content']	= urldecode($item['content']);
		}
		
		return $commentList;
	}
}