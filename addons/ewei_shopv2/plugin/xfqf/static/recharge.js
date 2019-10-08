define(['core', 'tpl'], function (core, tpl) {
    var modal = {minimumcharge: 0, wechat: 0, alipay: 0};
    modal.init = function (params) {
        modal.minimumcharge = params.minimumcharge;
        modal.wechat = params.wechat;
        modal.alipay = params.alipay;
        modal.credit = params.credit;

        $('#btn-next').click(function () {
            var money = $.trim($('#money').val());
            var showpay = false;

            if ($(this).attr('submit')) {
                return
            }
            if (!$.isEmpty(money)) {
                if ($.isNumber(money) && parseFloat(money) > 0) {
                    if (modal.minimumcharge > 0) {
                        if (parseFloat(money) < modal.minimumcharge) {
                            FoxUI.toast.show('最低充值金额为' + modal.minimumcharge + '元!');
                            return
                        } else {
                            showpay = true
                        }

                    } else {
                        showpay = true
                    }
                }
            }
            if (!showpay) {
                return
            }
            $('#btn-next').hide();
            if (core.ish5app()) {
                $('#btn-wechat').show();
                $('#btn-alipay').show();
                return;
            }
            if (modal.wechat) {
                $('#btn-wechat').show()
            }
            if (modal.alipay) {
                $('#btn-alipay').show()
            }
            if (modal.credit) {
                $('#btn-credit').show();
            }
        });

        $(document).on('click', '#btn-wechat', function () {
            if ($('.btn-pay').attr('submit')) {
                return
            }
            var money = $('#money').val();
            var mid = $('#mid').val();
            if (money <= 0) {
                FoxUI.toast.show('充值金额必须大于0!');
                return
            }
            if (!$('#money').isNumber()) {
                FoxUI.toast.show('请输入数字金额!');
                return
            }
            $('.btn-pay').attr('submit', 1);
            core.json('xfqf/buy/submit', {
                type: 'wechat',
                money: money,
                mid: mid
            }, function (rjson) {
                if (rjson.status != 1) {
                    $('.btn-pay').removeAttr('submit');
                    FoxUI.toast.show(rjson.result.message);
                    return
                }
                if (core.ish5app()) {
                    appPay('wechat', rjson.result.logno, rjson.result.money, true);
                    return;
                }
                var wechat = rjson.result.wechat;
                console.info(wechat);
                if (wechat.weixin) {
                    function onBridgeReady() {
                        WeixinJSBridge.invoke('getBrandWCPayRequest', {
                            'appId': wechat.appid ? wechat.appid : wechat.appId,
                            'timeStamp': wechat.timeStamp,
                            'nonceStr': wechat.nonceStr,
                            'package': wechat.package,
                            'signType': wechat.signType,
                            'paySign': wechat.paySign
                        }, function (res) {
                            if (res.err_msg == 'get_brand_wcpay_request:ok') {

                                core.json('xfqf/buy/wechat_complete', {logid: rjson.result.logid}, function (pay_json) {
                                    if (pay_json.status == 1) {
                                        FoxUI.toast.show('充值成功!');
                                        location.href = core.getUrl('member');
                                        return
                                    }
                                    FoxUI.toast.show(pay_json.result.message);
                                    $('.btn-pay').removeAttr('submit')
                                }, true, true)
                            } else if (res.err_msg == 'get_brand_wcpay_request:cancel') {
                                $('.btn-pay').removeAttr('submit');
                                FoxUI.toast.show('取消支付')
                            }

                        })
                    }

                    if (typeof WeixinJSBridge == "undefined") {
                        if (document.addEventListener) {
                            document.addEventListener('WeixinJSBridgeReady', onBridgeReady, false);
                        } else if (document.attachEvent) {
                            document.attachEvent('WeixinJSBridgeReady', onBridgeReady);
                            document.attachEvent('onWeixinJSBridgeReady', onBridgeReady);
                        }
                    } else {
                        onBridgeReady();
                    }
                }
                if (wechat.weixin_jie || wechat.jie == 1) {
                    modal.payWechatJie(rjson.result, money)
                }
            }, true, true)
        });
        $(document).on('click', '#btn-alipay', function () {
            if ($('.btn-pay').attr('submit') && !core.ish5app()) {
                return
            }
            if (money <= 0) {
                FoxUI.toast.show('充值金额必须大于0!');
                return
            }
            var money = $('#money').val();
            var mid = $('#mid').val();
            if (!$('#money').isNumber()) {
                FoxUI.toast.show('请输入数字金额!');
                return
            }
            $('.btn-pay').attr('submit', 1);
            core.json('xfqf/buy/submit', {
                type: 'alipay',
                money: money,
                mid: mid
            }, function (rjson) {
                if (rjson.status != 1) {
                    $('.btn-pay').removeAttr('submit');
                    FoxUI.toast.show(rjson.result.message);
                    return
                }
                if (core.ish5app()) {
                    appPay('alipay', rjson.result.logno, money, '1', null, true);
                } else {
                    location.href = core.getUrl('order/pay_alipay', {
                        orderid: rjson.result.logno,
                        type: 1,
                        url: rjson.result.alipay.url
                    })
                }
            }, true, true)
        });

        $(document).on('click', '#btn-credit', function () {
            if ($('.btn-pay').attr('submit') && !core.ish5app()) {
                return
            }
            if (money <= 0) {
                FoxUI.toast.show('充值金额必须大于0!');
                return
            }
            var money = $('#money').val();
            var mid = $('#mid').val();
            if (!$('#money').isNumber()) {
                FoxUI.toast.show('请输入数字金额!');
                return
            }
            $('.btn-pay').attr('submit', 1);
            core.json('xfqf/buy/submit', {
                type: 'credit',
                money: money,
                mid: mid
            }, function (rjson) {
                if (rjson.status != 1) {
                    $('.btn-pay').removeAttr('submit');
                    FoxUI.toast.show(rjson.result.message);
                    return
                }

                location.href = core.getUrl('member');
                return

            }, true, true)
        })
    };
    modal.payWechatJie = function (res, money) {
        var img = core.getUrl('index/qr', {url: res.wechat.code_url});
        $('#qrmoney').text(money);
        $('#btnWeixinJieCancel').unbind('click').click(function () {
            $('.btn-pay').removeAttr('submit');
            clearInterval(settime);
            $('.order-weixinpay-hidden').hide()
        });
        $('.order-weixinpay-hidden').show();
        var settime = setInterval(function () {
            core.json('member/recharge/wechat_complete', {logid: res.logid}, function (pay_json) {
                if (pay_json.status == 1) {
                    location.href = core.getUrl('member');
                    return
                }
            }, false, true)
        }, 1000);
        $('.verify-pop').find('.close').unbind('click').click(function () {
            $('.order-weixinpay-hidden').hide();
            $('.btn-pay').removeAttr('submit');
            clearInterval(settime)
        });
        $('.verify-pop').find('.qrimg').attr('src', img).show()
    };
    return modal
});