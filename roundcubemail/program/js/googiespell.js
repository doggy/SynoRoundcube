var GOOGIE_CUR_LANG,GOOGIE_DEFAULT_LANG="en";
function GoogieSpell(x,y,z){var m=this,u=rcmail.get_cookie("language");GOOGIE_CUR_LANG=u!=null?u:GOOGIE_DEFAULT_LANG;this.array_keys=function(a){var b=[],c;for(c in a)b.push([c]);return b};this.img_dir=x;this.server_url=y;this.lang_to_word=this.org_lang_to_word={da:"Dansk",de:"Deutsch",en:"English",es:"Espa\u00f1ol",fr:"Fran\u00e7ais",it:"Italiano",nl:"Nederlands",pl:"Polski",pt:"Portugu\u00eas",ru:"\u0420\u0443\u0441\u0441\u043a\u0438\u0439",fi:"Suomi",sv:"Svenska"};this.langlist_codes=this.array_keys(this.lang_to_word);
this.show_change_lang_pic=true;this.change_lang_pic_placement="right";this.report_state_change=true;this.el_scroll_top=this.ta_scroll_top=0;this.lang_chck_spell="Check spelling";this.lang_revert="Revert to";this.lang_close="Close";this.lang_rsm_edt="Resume editing";this.lang_no_error_found="No spelling errors found";this.lang_no_suggestions="No suggestions";this.lang_learn_word="Add to dictionary";this.show_spell_img=false;this.decoration=true;this.use_close_btn=false;this.report_ta_not_found=this.edit_layer_dbl_click=
true;this.custom_no_spelling_error=this.custom_ajax_error=null;this.custom_menu_builder=[];this.custom_item_evaulator=null;this.extra_menu_items=[];this.custom_spellcheck_starter=null;this.main_controller=true;this.has_dictionary=z;this.all_errors_fixed_observer=this.show_menu_observer=this.spelling_state_observer=this.lang_state_observer=null;this.use_focus=false;this.focus_link_b=this.focus_link_t=null;this.cnt_errors_fixed=this.cnt_errors=0;$(document).bind("click",function(a){a=$(a.target);a.attr("googie_action_btn")!=
"1"&&m.isLangWindowShown()&&m.hideLangWindow();a.attr("googie_action_btn")!="1"&&m.isErrorWindowShown()&&m.hideErrorWindow()});this.decorateTextarea=function(a){if(this.text_area=typeof a==="string"?document.getElementById(a):a){if(!this.spell_container&&this.decoration){a=document.createElement("table");var b=document.createElement("tbody"),c=document.createElement("tr"),d=document.createElement("td"),e=this.isDefined(this.force_width)?this.force_width:this.text_area.offsetWidth,f=this.isDefined(this.force_height)?
this.force_height:16;c.appendChild(d);b.appendChild(c);$(a).append(b).insertBefore(this.text_area).width("100%").height(f);$(d).height(f).width(e).css("text-align","right");this.spell_container=d}this.checkSpellingState()}else this.report_ta_not_found&&alert("Text area not found")};this.setSpellContainer=function(a){this.spell_container=typeof a==="string"?document.getElementById(a):a};this.setLanguages=function(a){this.lang_to_word=a;this.langlist_codes=this.array_keys(a)};this.setCurrentLanguage=
function(a){GOOGIE_CUR_LANG=a;var b=new Date;b.setTime(b.getTime()+31536E6);rcmail.set_cookie("language",a,b)};this.setForceWidthHeight=function(a,b){this.force_width=a;this.force_height=b};this.setDecoration=function(a){this.decoration=a};this.dontUseCloseButtons=function(){this.use_close_btn=false};this.appendNewMenuItem=function(a,b,c){this.extra_menu_items.push([a,b,c])};this.appendCustomMenuBuilder=function(a,b){this.custom_menu_builder.push([a,b])};this.setFocus=function(){try{this.focus_link_b.focus();
this.focus_link_t.focus();return true}catch(a){return false}};this.setStateChanged=function(a){this.state=a;this.spelling_state_observer!=null&&this.report_state_change&&this.spelling_state_observer(a,this)};this.setReportStateChange=function(a){this.report_state_change=a};this.getUrl=function(){return this.server_url+GOOGIE_CUR_LANG};this.escapeSpecial=function(a){return a?a.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;"):""};this.createXMLReq=function(a){return'<?xml version="1.0" encoding="utf-8" ?><spellrequest textalreadyclipped="0" ignoredups="0" ignoredigits="1" ignoreallcaps="1"><text>'+
a+"</text></spellrequest>"};this.spellCheck=function(a){this.prepare(a);a=this.escapeSpecial(this.orginal_text);var b=this;$.ajax({type:"POST",url:this.getUrl(),data:this.createXMLReq(a),dataType:"text",error:function(){b.custom_ajax_error?b.custom_ajax_error(b):alert("An error was encountered on the server. Please try again later.");if(b.main_controller){$(b.spell_span).remove();b.removeIndicator()}b.checkSpellingState()},success:function(c){b.processData(c);b.results.length||(b.custom_no_spelling_error?
b.custom_no_spelling_error(b):b.flashNoSpellingErrorState());b.removeIndicator()}})};this.learnWord=function(a){a=this.escapeSpecial(a.innerHTML);var b=this;a='<?xml version="1.0" encoding="utf-8" ?><learnword><text>'+a+"</text></learnword>";$.ajax({type:"POST",url:this.getUrl(),data:a,dataType:"text",error:function(){b.custom_ajax_error?b.custom_ajax_error(b):alert("An error was encountered on the server. Please try again later.")},success:function(){}})};this.prepare=function(a,b){this.cnt_errors=
this.cnt_errors_fixed=0;this.setStateChanged("checking_spell");!b&&this.main_controller&&this.appendIndicator(this.spell_span);this.error_links=[];this.ta_scroll_top=this.text_area.scrollTop;this.ignore=a;this.hideLangWindow();if($(this.text_area).val()==""||a){this.custom_no_spelling_error?this.custom_no_spelling_error(this):this.flashNoSpellingErrorState();this.removeIndicator()}else{this.createEditLayer(this.text_area.offsetWidth,this.text_area.offsetHeight);this.createErrorWindow();$("body").append(this.error_window);
try{netscape.security.PrivilegeManager.enablePrivilege("UniversalBrowserRead")}catch(c){}this.main_controller&&$(this.spell_span).unbind("click");this.orginal_text=$(this.text_area).val()}};this.parseResult=function(a){var b=/\w+="(\d+|true)"/g,c=/\t/g;a=a.match(/<c[^>]*>[^<]*<\/c>/g);var d=[];if(a==null)return d;for(var e=0,f=a.length;e<f;e++){var j=[];this.errorFound();j.attrs=[];for(var g,h,n=a[e].match(b),k=0;k<n.length;k++){g=n[k].split(/=/);h=g[1].replace(/"/g,"");j.attrs[g[0]]=h!="true"?parseInt(h):
h}j.suggestions=[];g=a[e].replace(/<[^>]*>/g,"").split(c);for(h=0;h<g.length;h++)g[h]!=""&&j.suggestions.push(g[h]);d.push(j)}return d};this.processData=function(a){this.results=this.parseResult(a);if(this.results.length){this.showErrorsInIframe();this.resumeEditingState()}};this.createErrorWindow=function(){this.error_window=document.createElement("div");$(this.error_window).addClass("googie_window popupmenu").attr("googie_action_btn","1")};this.isErrorWindowShown=function(){return $(this.error_window).is(":visible")};
this.hideErrorWindow=function(){$(this.error_window).hide();$(this.error_window_iframe).hide()};this.updateOrginalText=function(a,b,c,d){var e=this.orginal_text.substring(0,a);a=this.orginal_text.substring(a+b.length);b=c.length-b.length;this.orginal_text=e+c+a;$(this.text_area).val(this.orginal_text);c=0;for(e=this.results.length;c<e;c++)if(c!=d&&c>d)this.results[c].attrs.o+=b};this.saveOldValue=function(a,b){a.is_changed=true;a.old_value=b};this.createListSeparator=function(){var a=document.createElement("td"),
b=document.createElement("tr");$(a).html(" ").attr("googie_action_btn","1").css({cursor:"default","font-size":"3px","border-top":"1px solid #ccc","padding-top":"3px"});b.appendChild(a);return b};this.correctError=function(a,b,c,d){var e=b.innerHTML;c=c.nodeType==3?c.nodeValue:c.innerHTML;var f=this.results[a].attrs.o;if(d){d=b.previousSibling.innerHTML;b.previousSibling.innerHTML=d.slice(0,d.length-1);e=" "+e;f--}this.hideErrorWindow();this.updateOrginalText(f,e,c,a);$(b).html(c).css("color","green").attr("is_corrected",
true);this.results[a].attrs.l=c.length;this.isDefined(b.old_value)||this.saveOldValue(b,e);this.errorFixed()};this.ignoreError=function(a){$(a).removeAttr("class").css("color","").unbind();this.hideErrorWindow()};this.showErrorWindow=function(a,b){this.show_menu_observer&&this.show_menu_observer(this);var c=this,d=$(a).offset(),e=document.createElement("table"),f=document.createElement("tbody");$(this.error_window).html("");$(e).addClass("googie_list").attr("googie_action_btn","1");for(var j=false,
g=0;g<this.custom_menu_builder.length;g++){var h=this.custom_menu_builder[g];if(h[0](this.results[b])){j=h[1](this,f,a);break}}if(!j){j=this.results[b].suggestions;var n=this.results[b].attrs.o;g=this.results[b].attrs.l;var k,l;if(this.has_dictionary&&!$(a).attr("is_corrected")){h=document.createElement("tr");k=document.createElement("td");l=document.createElement("span");$(l).text(this.lang_learn_word);$(k).attr("googie_action_btn","1").css("cursor","default").mouseover(c.item_onmouseover).mouseout(c.item_onmouseout).click(function(){c.learnWord(a,
b);c.ignoreError(a,b)});k.appendChild(l);h.appendChild(k);f.appendChild(h)}var r=0;for(g=j.length;r<g;r++){h=document.createElement("tr");k=document.createElement("td");l=document.createElement("span");$(l).html(j[r]);$(k).mouseover(this.item_onmouseover).mouseout(this.item_onmouseout).click(function(p){c.correctError(b,a,p.target.firstChild)});k.appendChild(l);h.appendChild(k);f.appendChild(h)}if(a.is_changed&&a.innerHTML!=a.old_value){var s=a.old_value;j=document.createElement("tr");g=document.createElement("td");
h=document.createElement("span");$(h).addClass("googie_list_revert").html(this.lang_revert+" "+s);$(g).mouseover(this.item_onmouseover).mouseout(this.item_onmouseout).click(function(){c.updateOrginalText(n,a.innerHTML,s,b);$(a).removeAttr("is_corrected").css("color","#b91414").html(s);c.hideErrorWindow()});g.appendChild(h);j.appendChild(g);f.appendChild(j)}j=document.createElement("tr");g=document.createElement("td");var o=document.createElement("input");h=document.createElement("img");k=document.createElement("form");
l=function(){if(o.value!=""){c.isDefined(a.old_value)||c.saveOldValue(a,a.innerHTML);c.updateOrginalText(n,a.innerHTML,o.value,b);$(a).attr("is_corrected",true).css("color","green").html(o.value);c.hideErrorWindow()}return false};$(o).width(120).css({margin:0,padding:0});$(o).val(a.innerHTML).attr("googie_action_btn","1");$(g).css("cursor","default").attr("googie_action_btn","1");$(h).attr("src",this.img_dir+"ok.gif").width(32).height(16).css({cursor:"pointer","margin-left":"2px","margin-right":"2px"}).click(l);
$(k).attr("googie_action_btn","1").css({margin:0,padding:0,cursor:"default","white-space":"nowrap"}).submit(l);k.appendChild(o);k.appendChild(h);g.appendChild(k);j.appendChild(g);f.appendChild(j);this.extra_menu_items.length>0&&f.appendChild(this.createListSeparator());var t=function(p){if(p<c.extra_menu_items.length){var q=c.extra_menu_items[p];if(!q[2]||q[2](a,c)){var v=document.createElement("tr"),w=document.createElement("td");$(w).html(q[0]).mouseover(c.item_onmouseover).mouseout(c.item_onmouseout).click(function(){return q[1](a,
c)});v.appendChild(w);f.appendChild(v)}t(p+1)}};t(0);t=null;this.use_close_btn&&f.appendChild(this.createCloseButton(this.hideErrorWindow))}e.appendChild(f);this.error_window.appendChild(e);g=$(this.error_window).height();e=$(this.error_window).width();h=$(document).height();j=$(document).width();g=d.top+g+20<h?d.top+20:d.top-g;d=d.left+e<j?d.left:d.left-e;$(this.error_window).css({top:g+"px",left:d+"px"}).show();if($.browser.msie){if(!this.error_window_iframe){d=$("<iframe>").css({position:"absolute",
"z-index":-1});$("body").append(d);this.error_window_iframe=d}$(this.error_window_iframe).css({top:this.error_window.offsetTop,left:this.error_window.offsetLeft,width:this.error_window.offsetWidth,height:this.error_window.offsetHeight}).show()}};this.createEditLayer=function(a,b){this.edit_layer=document.createElement("div");$(this.edit_layer).addClass("googie_edit_layer").attr("id","googie_edit_layer").width("auto").height(b);this.text_area.nodeName.toLowerCase()!="input"||$(this.text_area).val()==
""?$(this.edit_layer).css("overflow","auto").height(b-4):$(this.edit_layer).css("overflow","hidden");var c=this;this.edit_layer_dbl_click&&$(this.edit_layer).dblclick(function(d){if(d.target.className!="googie_link"&&!c.isErrorWindowShown()){c.resumeEditing();var e=function(){$(c.text_area).focus();e=null};window.setTimeout(e,10)}return false})};this.resumeEditing=function(){this.setStateChanged("ready");if(this.edit_layer)this.el_scroll_top=this.edit_layer.scrollTop;this.hideErrorWindow();this.main_controller&&
$(this.spell_span).removeClass().addClass("googie_no_style");if(!this.ignore){if(this.use_focus){$(this.focus_link_t).remove();$(this.focus_link_b).remove()}$(this.edit_layer).remove();$(this.text_area).show();if(this.el_scroll_top!=undefined)this.text_area.scrollTop=this.el_scroll_top}this.checkSpellingState(false)};this.createErrorLink=function(a,b){var c=document.createElement("span"),d=this,e=function(){d.showErrorWindow(c,b);e=null;return false};$(c).html(a).addClass("googie_link").click(e).removeAttr("is_corrected").attr({googie_action_btn:"1",
g_id:b});return c};this.createPart=function(a){if(a==" ")return document.createTextNode(" ");a=this.escapeSpecial(a);a=a.replace(/\n/g,"<br>");a=a.replace(/    /g," &nbsp;");a=a.replace(/^ /g,"&nbsp;");a=a.replace(/ $/g,"&nbsp;");var b=document.createElement("span");$(b).html(a);return b};this.showErrorsInIframe=function(){var a=document.createElement("div"),b=0,c=this.results;if(c.length>0){for(var d=0,e=c.length;d<e;d++){var f=c[d].attrs.o,j=c[d].attrs.l,g=this.createPart(this.orginal_text.substring(b,
f));a.appendChild(g);b+=f-b;f=this.createErrorLink(this.orginal_text.substr(f,j),d);this.error_links.push(f);a.appendChild(f);b+=j}b=this.createPart(this.orginal_text.substr(b,this.orginal_text.length));a.appendChild(b)}else a.innerHTML=this.orginal_text;$(a).css("text-align","left");var h=this;this.custom_item_evaulator&&$.map(this.error_links,function(n){h.custom_item_evaulator(h,n)});$(this.edit_layer).append(a);$(this.text_area).hide();$(this.edit_layer).insertBefore(this.text_area);if(this.use_focus){this.focus_link_t=
this.createFocusLink("focus_t");this.focus_link_b=this.createFocusLink("focus_b");$(this.focus_link_t).insertBefore(this.edit_layer);$(this.focus_link_b).insertAfter(this.edit_layer)}};this.createLangWindow=function(){this.language_window=document.createElement("div");$(this.language_window).addClass("googie_window popupmenu").width(100).attr("googie_action_btn","1");var a=document.createElement("table"),b=document.createElement("tbody"),c=this,d,e,f;$(a).addClass("googie_list").width("100%");this.lang_elms=
[];for(i=0;i<this.langlist_codes.length;i++){d=document.createElement("tr");e=document.createElement("td");f=document.createElement("span");$(f).text(this.lang_to_word[this.langlist_codes[i]]);this.lang_elms.push(e);$(e).attr("googieId",this.langlist_codes[i]).bind("click",function(){c.deHighlightCurSel();c.setCurrentLanguage($(this).attr("googieId"));c.lang_state_observer!=null&&c.lang_state_observer();c.highlightCurSel();c.hideLangWindow()}).bind("mouseover",function(){if(this.className!="googie_list_selected")this.className=
"googie_list_onhover"}).bind("mouseout",function(){if(this.className!="googie_list_selected")this.className="googie_list_onout"});e.appendChild(f);d.appendChild(e);b.appendChild(d)}this.use_close_btn&&b.appendChild(this.createCloseButton(function(){c.hideLangWindow.apply(c)}));this.highlightCurSel();a.appendChild(b);this.language_window.appendChild(a)};this.isLangWindowShown=function(){return $(this.language_window).is(":visible")};this.hideLangWindow=function(){$(this.language_window).hide();$(this.switch_lan_pic).removeClass().addClass("googie_lang_3d_on")};
this.showLangWindow=function(a){this.show_menu_observer&&this.show_menu_observer(this);this.createLangWindow();$("body").append(this.language_window);var b=$(a).offset(),c=$(a).height(),d=$(a).width();a=$(this.language_window).height();var e=$(document).height();d=this.change_lang_pic_placement=="right"?b.left-100+d:b.left+d;b=b.top+a<e?b.top+c:b.top-a-4;$(this.language_window).css({top:b+"px",left:d+"px"}).show();this.highlightCurSel()};this.deHighlightCurSel=function(){$(this.lang_cur_elm).removeClass().addClass("googie_list_onout")};
this.highlightCurSel=function(){if(GOOGIE_CUR_LANG==null)GOOGIE_CUR_LANG=GOOGIE_DEFAULT_LANG;for(var a=0;a<this.lang_elms.length;a++)if($(this.lang_elms[a]).attr("googieId")==GOOGIE_CUR_LANG){this.lang_elms[a].className="googie_list_selected";this.lang_cur_elm=this.lang_elms[a]}else this.lang_elms[a].className="googie_list_onout"};this.createChangeLangPic=function(){var a=$("<img>").attr({src:this.img_dir+"change_lang.gif",alt:"Change language",googie_action_btn:"1"}),b=document.createElement("span");
m=this;$(b).addClass("googie_lang_3d_on").append(a).bind("click",function(){var c=this.tagName.toLowerCase()=="img"?this.parentNode:this;if($(c).hasClass("googie_lang_3d_click")){c.className="googie_lang_3d_on";m.hideLangWindow()}else{c.className="googie_lang_3d_click";m.showLangWindow(c)}});return b};this.createSpellDiv=function(){var a=document.createElement("span");$(a).addClass("googie_check_spelling_link").text(this.lang_chck_spell);this.show_spell_img&&$(a).append(" ").append($("<img>").attr("src",
this.img_dir+"spellc.gif"));return a};this.flashNoSpellingErrorState=function(a){this.setStateChanged("no_error_found");var b=this;if(this.main_controller){var c;c=a?function(){a();b.checkSpellingState()}:function(){b.checkSpellingState()};var d=$("<span>").text(this.lang_no_error_found);$(this.switch_lan_pic).hide();$(this.spell_span).empty().append(d).removeClass().addClass("googie_check_spelling_ok");window.setTimeout(c,1E3)}};this.resumeEditingState=function(){this.setStateChanged("resume_editing");
if(this.main_controller){var a=$("<span>").text(this.lang_rsm_edt),b=this;$(this.switch_lan_pic).hide();$(this.spell_span).empty().unbind().append(a).bind("click",function(){b.resumeEditing()}).removeClass().addClass("googie_resume_editing")}try{this.edit_layer.scrollTop=this.ta_scroll_top}catch(c){}};this.checkSpellingState=function(a){a&&this.setStateChanged("ready");this.switch_lan_pic=this.show_change_lang_pic?this.createChangeLangPic():document.createElement("span");a=this.createSpellDiv();var b=
this;this.custom_spellcheck_starter?$(a).bind("click",function(){b.custom_spellcheck_starter()}):$(a).bind("click",function(){b.spellCheck()});if(this.main_controller)this.change_lang_pic_placement=="left"?$(this.spell_container).empty().append(this.switch_lan_pic).append(" ").append(a):$(this.spell_container).empty().append(a).append(" ").append(this.switch_lan_pic);this.spell_span=a};this.isDefined=function(a){return a!==undefined&&a!==null};this.errorFixed=function(){this.cnt_errors_fixed++;if(this.all_errors_fixed_observer)if(this.cnt_errors_fixed==
this.cnt_errors){this.hideErrorWindow();this.all_errors_fixed_observer()}};this.errorFound=function(){this.cnt_errors++};this.createCloseButton=function(a){return this.createButton(this.lang_close,"googie_list_close",a)};this.createButton=function(a,b,c){var d=document.createElement("tr"),e=document.createElement("td"),f;if(b){f=document.createElement("span");$(f).addClass(b).html(a)}else f=document.createTextNode(a);$(e).bind("click",c).bind("mouseover",this.item_onmouseover).bind("mouseout",this.item_onmouseout);
e.appendChild(f);d.appendChild(e);return d};this.removeIndicator=function(){window.rcmail&&rcmail.set_busy(false,null,this.rc_msg_id)};this.appendIndicator=function(){if(window.rcmail)this.rc_msg_id=rcmail.set_busy(true,"checking")};this.createFocusLink=function(a){var b=document.createElement("a");$(b).attr({href:"javascript:;",name:a});return b};this.item_onmouseover=function(){if(this.className!="googie_list_revert"&&this.className!="googie_list_close")this.className="googie_list_onhover";else this.parentNode.className=
"googie_list_onhover"};this.item_onmouseout=function(){if(this.className!="googie_list_revert"&&this.className!="googie_list_close")this.className="googie_list_onout";else this.parentNode.className="googie_list_onout"}};
