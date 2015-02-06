DLE TABS FOR CSS
=
> Данный хак позволяет добавлять в новость вкладки. Их полезность заключается в том, что они позволяют существенно сократить площадь, занимаемую информацией, выводимой в новости. К тому же это оригинально и красиво.
> **Работает для DLE версий 10.x. На версиях ниже 10.0 не проверялся и работоспособность не гарантирована.**
> Автор для 9.x: **rocksmart**. Доработка и исправление **by MaD**

**Пример кода табов**
```
[tab]

[tabs=tab1]text1[/tabs]
[tabs=tab2]text2[/tabs]
[tabs=tab3]text3[/tabs]
[tabs=tab4]text4[/tabs]

[/tab]
```

> Если у вас версия движка **DLE 10.4 ** и вы не вносили у файл ``/engine/classes/parse.class.php`` правки, 
то нужно просто залить тот который лежит у папке ``/engine/classes/`` и согласится на замену.

#Инструкция по установке DLE Табов
--------------
**1.** Открываем файл для редактирования по адресу ``/engine/classes/parse.class.php``

**1.2** После ( ~272 строка)
```php
if( $this->filter_mode ) $source = $this->word_filter( $source );
```

**1.3** Вставляем
```php
$source = $this->build_tabs($source, true);
```

**1.4** После ( ~431 строка)
```php
if( $this->filter_mode ) $txt = $this->word_filter( $txt, false );
```

**1.5** Вставляем
```php
$txt = $this->build_tabs($txt, false);
```

**1.6** Перед функцией function ``build_list(``

```php
function build_list( $matches=array() ) {
```
**1.7** Вставляем 
```php
function build_tabs($source, $html = ""){
	if ($html == TRUE) {

		$count_tab = preg_match_all("#\[tab\](.+?)\[/tab\]#is", $source, $tab);
		$num_tab = 1;

		for ($x = 0; $x < $count_tab; $x++) {

			$count_tabs = preg_match_all("#\[tabs=(.*?)\](.*?)\[/tabs\]#si", $tab[1][$x], $tabs);

			for ($i = 0; $i < $count_tabs; $i++) {

				$checked = ($num_tab == 1)? 'checked': '';
				$title = "<input id=\"tab{$num_tab}\" type=\"radio\" name=\"tabs\" {$checked}><label for=\"tab{$num_tab}\" title=".$tabs[1][$i].">".$tabs[1][$i]."</label>";
				$text  = "<section id=\"content{$num_tab}\">" . $tabs[2][$i] . "</section>";

				$tabs2[$x] .= $text;
				$tabs1[$x] .= $title;
				$num_tab++;
			}
			$source = str_replace($tab[0][$x], "<!--TabsBegin--><div class=\"tabs\">".$tabs1[$x].$tabs2[$x]."</div><!--/TabsEnd-->", $source);
		}
	} else {
		$count_tb = preg_match_all("#<!--TabsBegin--><div class=\"tabs\">(.*)<\/div><!--\/TabsEnd-->#si", $source, $tb);

		for ($x=0; $x< $count_tb; $x++){

			preg_match_all("#<label(.+?)>(.+?)</label>#si",$tb[1][$x], $title);
			preg_match_all("#<section(.+?)>(.+?)</section>#si",$tb[1][$x], $text);

			for ($i = 0; $i < count($title[2]); $i++) {

				$tabs2[$x] .="[tabs=".$title[2][$i]."]".$text[2][$i]."[/tabs]\n";
			}
			$source = str_replace($tb[0][$x],"[tab]\n".$tabs2[$x]."[/tab]",$source);
		}
	}
	return $source;
}
```
**2.** Далее открываем файл для редактирования ``/engine/inc/include/inserttag.php``

**2.1** После ( ~641 строка)
```js
-->
</SCRIPT>
```

**2.2** Вставляем 
```js
<script type=text/javascript>
function insert_tabs(){
doInsert("[tab]\\n[tabs=Таб1]Таб1[/tabs]\\n[tabs=Таб2]Таб2[/tabs]\\n[tabs=Таб3]Таб3[/tabs]\\n[tabs=Таб4]Таб4[/tabs]\\n[/tab]", "", true );
}
</script>
```

**2.3** 
После ( ~649 строка)
```html
<div id="b_s" class="editor_button" onclick="simpletag('s')"><img title="$lang[bb_t_s]" src="engine/skins/bbcodes/images/s.gif" width="23" height="25" border="0"></div>
```

**2.4** Вставляем это кусок кода
```html
<div id="b_tabs" class="editor_button" onclick='insert_tabs()'><img title="Вставка табов" src="engine/skins/bbcodes/images/t.png" width="23" height="25" border="0"></div>
```

**3.** Далее открываем файл для редактирования ``/templates/ИМЯ ВАШЕГО ШАБЛОНА/css/engine.css`` или любой другой css файл который подключается в main.tpl

**3.1** И в самый конец файла вставляем
```css
/* Базовый контейнер табов */
.tabs {
  min-width: 320px;
  max-width: 800px;
  padding: 0px;
  margin: 0 auto;
  
}
/* Стили секций с содержанием */
section {
  display: none;
  padding: 15px;
  background: #fff;
  border: 1px solid #ddd;
}
.tabs input {
  display: none;
}
/* Стили вкладок (табов) */
.tabs label {
  display: inline-block;
  margin: 0 0 -1px;
  padding: 15px 25px;
  font-weight: 600;
  text-align: center;
  color: #aaa;
  border: 1px solid #ddd;
  background: #f1f1f1;
  border-radius: 3px 3px 0 0;
}
/* Шрифт-иконки от Font Awesome в формате Unicode */
.tabs label:before {
  font-family: fontawesome;
  font-weight: normal;
  margin-right: 10px;
}
.tabs label[for*="1"]:before {
  content: "\f19a";
}
.tabs label[for*="2"]:before {
  content: "\f17a";
}
.tabs label[for*="3"]:before {
  content: "\f13b";
}
.tabs label[for*="4"]:before {
  content: "\f13c";
}
/* изменения стиля заголовков вкладок при наведении */
.tabs label:hover {
  color: #888;
  cursor: pointer;
}
/* стили для активной вкладки */
.tabs input:checked + label {
  color: #555;
  border: 1px solid #ddd;
  border-top: 1px solid #009933;
  border-bottom: 1px solid #fff;
  background: #fff;
}
/* активация секций с помощью переключателя :checked */
#tab1:checked ~ #content1,
#tab2:checked ~ #content2,
#tab3:checked ~ #content3,
#tab4:checked ~ #content4 {
  display: block;
}
/* медиа запросы для различных типов носителей */  
@media screen and (max-width: 680px) {
  .tabs label {
    font-size: 0;
  }

  .tabs label:before {
    margin: 0;
    font-size: 18px;
  }
}
@media screen and (max-width: 400px) {
  .tabs label {
    padding: 15px;
  }
}
```

**5. Установка закончена**

Скрины модуля
-------
![Вкладка 2](http://s019.radikal.ru/i609/1502/85/0fb7b6a17772.png)
![Вкладка 1](http://s019.radikal.ru/i601/1502/6b/c0da8a602b45.png)
![Табы при редактировании](http://s52.radikal.ru/i135/1502/a4/bc3bf87e4193.png)
![Исходник табов](http://s017.radikal.ru/i419/1502/0f/03443ca76910.png)