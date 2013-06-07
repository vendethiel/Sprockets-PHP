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
