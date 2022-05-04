<?php
namespace BotsLogic; 

use SubscriberMessage;

class Antispam extends DefaultLogic {

	public $bot;
	public $whitelist_ids = [];
	public $whitelist_names = [];
	public $names = [
		'XXXo888888',
		'Cristall',
		'3a-fb',
		'ManyBenG',
		'CristYSend',
		'BbitMainN',
		'neutral_',
	];
	public $link = [
		'@',
		'bot',
		'http',
		'\.(ru|info)',
		'лс',
	];
	public $links = [
		'clck.ru/[^ ]+',
		't\.me/(\+|joinchat)',
		'TGSENDER',
		'\?ref=',
		'1000.*telegra.ph/[^ ]+',
	];
	public $english = [
		'Interested +can +',
		'send +Hi',
		'FOR +Sell',
		'Join .*if',
		'Our +website',
		'We.*(looking|seeing)',
		'Ping +me',
		'Crypto',
		'Forex',
	];
	public $sells = [
		'По +цене +(?:в|и +.+?) +лс',
		'за +(ценой|прайсом).+в +л',
		'прoмoкoд',
		'Скидкa +',
		'(доступным|низким) +ценам',
		'оплата +после',
		'(?<!не )прода(ю|м|жа|жи) +[^ ]{2,}',
		'В +(прoдаже|наличии)',
		'(?<!не )могу +продать',
		'^.{0,10}куплю +',
		'купим +[^ ]{2,}',
		'хотите .*заработать',
	];
	public $introduces = [
		'(Шарю +в +(BAS|БАС)',
		'(Сдела|Напиш|Настро)(ю|у|ем) .*(софт|приложени|оформлени|дизайн|прогрев|сторис|реклам|администриров)',
		'(Предлага|Предоставля|Оказыва)(ю|ем) .*услуги',
		'Требу(ю|е)тся +',
		'Учим +',
		'ПЗРД',
		'Ищ(у|ем) .*(ребят|работ|человек|начинающ|арбитражник|аффилиейт|новичк|веб(а|ов)|фармер|траф|ТАРГЕТОЛОГ|креативщ|единомыш|сотрудн|баер)',
		'Ищ(у|ем) .*людей[ ,]+(для|кто|под|которые|в )',
		'Нуж(ен|ны) +(люди|аффилейт)',
		'набор +сотрудников',
		'подскажу +как',
		'Наш +сайт',
		'Кoнтaкты.*:',
		'Линк +в +био',
		'Накрутка +подписчиков',
		'Взлом +соц',
	];
	public $invites = [
		'вступай',
		'кому +интересн',
		'Предлагаем',
		'Настраиваем',
		'Интегрируем',
		'Брендируем',
		'гарантируем',
		'Ищем',
		'ждём +Вас',
		'расскажем',
		'покажем',
		'ОБУЧИМ',
		'специализируемся',
		'сопровождать',
		'В +обязанности',
		'сотрудничеств',
		'под +ключ',
		'забегаем',
	];

	public $emojis = [
		'☑','✅','✔','⛔','💥','📈','♨','❇','🤑','🥬','♻️','🔫','💣','💸','👉','⚡','👨‍💻','🍋','^\.$'
	];

	/*
		$message - Object of class MysqlTableRow
		returns Boolean; // true - message deleted, false - message valid
	*/
	function antispam(SubscriberMessage $message) {

		if(
			in_array($message->platform_id, $this->whitelist_ids)
			||
			(
				!empty($this->whitelist_names)
				&& preg_match('~@('.implode('|', $this->whitelist_names).')~', $message->body)
			)
		) {
			// whitelist
			return false;

		} else if(
			// has @ in text
			// or has many unicode underlined symbols
			// or use cyrillic with latin symbols in one word
			// or forwarded from another group
			preg_match('~('.implode('|', $this->names).'|.{69,}@|(?:.\\x{035f}){5,})~isu', $message->body)
			|| (
				preg_match_all('~\w*(?:[а-я]+[a-z]+|[a-z]+[а-я]+)\w*~ui', $message->body, $m1)
				&& count($m1[0]) >= 5
			)
			|| (
				!empty($message->params['message']['forward_from_chat']['id'])
				&& !empty($message->params['message']['chat']['id'])
				&& $message->params['message']['forward_from_chat']['id'] != $message->params['message']['chat']['id']
			)
		) {

			$result = $this->bot->telegram()->deleteMessage([
				'message_id' => $message->platform_message_id,
				'chat_id' => $message->params['message']['chat']['id'] ?? $message->params['edited_message']['chat']['id'],
			]);

			if(!empty($result['ok'])) {
				$message->status = 'deleted';
				// $result = $this->bot->telegram()->banChatMember([
				// 	'user_id' => $message->params['message']['from']['id'],
				// 	'chat_id' => $message->params['message']['chat']['id'],
				// ]);

				$result = $this->bot->telegram()->restrictChatMember([
					'user_id' => $message->params['message']['from']['id'] ?? $message->params['edited_message']['from']['id'],
					'chat_id' => $message->params['message']['chat']['id'] ?? $message->params['edited_message']['chat']['id'],
					'permissions' => json_encode([
						'can_send_messages' => false,
						'can_send_media_messages' => false,
						'can_send_polls' => false,
						'can_send_other_messages' => false,
						'can_add_web_page_previews' => false,
						'can_change_info' => false,
						'can_invite_users' => false,
						'can_pin_messages' => false,
					]),
				]);

			}

			if(!empty($result['ok'])) {
				$message->status = 'restricted';
			}

			$message->result = null;
			$message->save();

			return true;

		} else if(
			(
				// long invites
				preg_match('~.{80,}~isu', $message->body)
				&& (
					preg_match('~(?<!не )('.implode('|', $this->invites).')~isu', $message->body)
					||
					// emoji
					preg_match('~('.implode('|', $this->emojis).')~isu', $message->body)
				)
			) || (
				// short introduces
				preg_match('~(?<!не )('.implode('|', $this->introduces).')~isu', $message->body)
			) || (
				// sell
				preg_match('~('.implode('|', $this->sells).')~isu', $message->body)
			) || (
				// english
				preg_match('~('.implode('|', $this->english).')~isu', $message->body)
			) || (
				// link
				preg_match('~👉.*('.implode('|', $this->link).')~isu', $message->body)
			) || (
				// links
				preg_match('~('.implode('|', $this->links).')~isu', $message->body)
			)
		){

			$result = $this->bot->telegram()->deleteMessage([
				'message_id' => $message->platform_message_id,
				'chat_id' => $message->params['message']['chat']['id'] ?? $message->params['edited_message']['chat']['id'],
			]);

			if(!empty($result['ok'])) {
				$message->status = 'deleted';
				$message->result = null;
				$message->save();
			}

			return true;
		}

		return false;
	}
}

?>
