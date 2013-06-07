window.jQuery = function () {
	
}
(function(){
  var Form, out$ = typeof exports != 'undefined' && exports || this;
  out$.Form = Form = (function(){
    Form.displayName = 'Form';
    var prototype = Form.prototype, constructor = Form;
    function Form(){}
    return Form;
  }());
  Form.Input = {};
}).call(this);

 

(function(){
  var Text;
  Form.Input.Text = Text = (function(){
    Text.displayName = 'Text';
    var prototype = Text.prototype, constructor = Text;
    prototype.type = 'Text';
    function Text(){}
    return Text;
  }());
}).call(this);
(function(){
  var Range;
  Form.Input.Range = Range = (function(){
    Range.displayName = 'Range';
    var prototype = Range.prototype, constructor = Range;
    prototype.type = 'Range';
    function Range(){}
    return Range;
  }());
}).call(this);

var stateImg = document.getElementById('stateImg')

setInterval(function () {
	ajax('/api/server/state', function (data) {
		stateImg.src = data
	})
}, 500)
