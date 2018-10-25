
 function addChangeLangEvent(){
   ChangeLangEvent();
   inputChangeLang();

    api.addEventListener({
  	    name: 'change_lang'
  	}, function(ret, err) {
        ChangeLangEvent();
        inputChangeLang();

  	});
  }


 function ChangeLangEvent(){
 	var LanguageType = api.getPrefs({
	    sync: true,
	    key: 'LanguageType'
	});

	var Language = {
			"China":China,
  		"English":English
  }

	var choose_langguage = Language[LanguageType];
	 for ( var x in choose_langguage) {
			$('#'+x).html(choose_langguage[x]);
			$('.'+x).html(choose_langguage[x]);
	 }
 }

 function inputChangeLang(){
	var LanguageType = api.getPrefs({
	    sync: true,
	    key: 'LanguageType'
	});
	var input_Language = {
			"China":input_China,
    	"English":input_English,
    	}
	var choose_langguage_input = input_Language[LanguageType];
	 for ( var x in choose_langguage_input) {
			$('#'+x).attr('placeholder',choose_langguage_input[x]);
      $('.'+x).attr('placeholder',choose_langguage_input[x]);
	 }
 }
