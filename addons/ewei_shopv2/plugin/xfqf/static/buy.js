define(['core', 'tpl'], function (core, tpl) {
    var modal = {};
    modal.init = function () {
        $('#btn-submit').click(function () {
            var postdata = {};
            var self = $(this);
            if (!$('#post_id').isInt()) {
                FoxUI.toast.show('请填写ID');
                return
            }
            if (!$('#post_price').isNumber()) {
                FoxUI.toast.show('请输入金额');
                return
            }

            if ($(this).attr('submit')) {
                return
            }

            var mid = parseInt($('#post_id').val());
            var price = parseFloat($('#post_price').val());

            if (price <= 0) {
                FoxUI.toast.show('请输入大于0的金额');
                return
            }


            self.html('提交中...').attr('submit', 1);

            core.json('xfqf/buy/member', {mid:mid,price:price}, function (json) {
                var status = parseInt(json.status);
                if(status>0){
                    $('#sub').submit();
                }else{
                    self.html('确认购买').removeAttr('submit');
                    FoxUI.toast.show(json.result.message);
                }
            }, true, true)


            /*
             var birthday = $('#birthday').val().split('-');
             var citys = $('#city').val().split(' ');

             $(this).html('处理中...').attr('submit', 1);
             postdata = {
             'memberdata': {
             'realname': $('#realname').val(),
             //'mobile': $('#mobile').val(),
             'weixin': $('#weixin').val(),
             'gender': $('#sex').val(),
             'birthyear': $('#birthday').val().length > 0 ? birthday[0] : 0,
             'birthmonth': $('#birthday').val().length > 0 ? birthday[1] : 0,
             'birthday': $('#birthday').val().length > 0 ? birthday[2] : 0,
             'province': $('#city').val().length > 0 ? citys[0] : '',
             'city': $('#city').val().length > 0 ? citys[1] : '',
             'datavalue': $('#city').attr('data-value')
             },
             'mcdata': {
             'realname': $('#realname').val(),
             //'mobile': $('#mobile').val(),
             'gender': $('#sex').val(),
             'birthyear': $('#birthday').val().length > 0 ? birthday[0] : 0,
             'birthmonth': $('#birthday').val().length > 0 ? birthday[1] : 0,
             'birthday': $('#birthday').val().length > 0 ? birthday[2] : 0,
             'resideprovince': $('#city').val().length > 0 ? citys[0] : '',
             'residecity': $('#city').val().length > 0 ? citys[1] : ''
             }
             };
             if(!params.wapopen){
             postdata.memberdata.mobile = $('#mobile').val();
             postdata.mcdata.mobile = $('#mobile').val();
             }
             core.json('member/info/submit', postdata, function (json) {
             modal.complete(params, json)
             }, true, true)*/

        })
    };

    modal.initFace = function () {

        $("#btn-submit").unbind('click').click(function () {
            var _this = $(this);
            if (_this.attr('stop')) {
                FoxUI.toast.show("保存中...");
                return;
            }
            var nickname = $.trim($("#nickname").val());
            var avatar = $.trim($("#avatar").data('filename'));
            if (nickname == '') {
                FoxUI.toast.show("请填写昵称");
                return;
            }
            if (avatar == '') {
                FoxUI.toast.show("请选择头像");
                return;
            }
            _this.attr('stop', 1);
            core.json('member/info/face', {nickname: nickname, avatar: avatar}, function (json) {
                if (json.status == 0) {
                    FoxUI.toast.show(json.result.message);
                } else {
                    window.memberData = {
                        nickname: nickname,
                        avatar: $.trim($("#avatar").attr('src'))
                    };
                    window.history.back();
                }
                _this.removeAttr('stop');
            }, true, true)
        });
    };

    return modal
});
