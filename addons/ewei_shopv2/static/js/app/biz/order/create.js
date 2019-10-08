define(['core', 'tpl', 'biz/plugin/diyform', 'biz/order/invoice'], function (core, tpl, diyform, invoice) {
    var modal = {
        params: {
            orderid: 0,
            goods: [],
            coupon_goods: [],
            merchs: [],
            iscarry: 0,
            isverify: 0,
            isvirtual: 0,
            isonlyverifygoods: 0,
            dispatch_price:0,
            deductenough_enough:0,
            merch_deductenough_enough:0,
            deductenough_money:0,
            merch_deductenough_money:0,
            addressid: 0,
            contype: 0,
            couponid: 0,
            card_id: 0,
            card_price: 0,
            wxid: 0,
            wxcardid: 0,
            wxcode: "",
            isnodispatch: 0,
            nodispatch: '',
            packageid: 0,
            card_packageid:0,
            new_area: '',
            address_street: '',
            discountprice:0,
            isdiscountprice:0,
            lotterydiscountprice:0,
            gift_price:0,
            giftid:0,
            show_card:false,
            city_express_state:false
        },
        invoice_info:{}
    };
    modal.init = function (params, invoice_info) {
        if (invoice_info && invoice_info.title){
            modal.invoice_info = invoice_info;
        }
        modal.params = $.extend(modal.params, params || {});
        modal.params.couponid = 0;
        $('#coupondiv').find('.fui-cell-label').html('优惠券');
        $('#coupondiv').find('.fui-cell-info').html('');
        var discountprice = core.getNumber($(".discountprice").val());
        var isdiscountprice = core.getNumber($(".isdiscountprice").val());
        if (discountprice > 0) {
            $('.discount').show();
        }
        if (isdiscountprice > 0) {
            $('.isdiscount').show();
        }

        if(modal.params.city_express_state){
            $('.fui-cell-group.city_express.external').show();
            $("#showdispatchprice div:first-child").text("同城运费");
        }else{

            $('.fui-cell-group.city_express.external').hide();
            $("#showdispatchprice div:first-child").text("运费");
        }

        var loadAddress = false;
        if (typeof(window.selectedAddressData) !== 'undefined') {
            loadAddress = window.selectedAddressData
        } else if (typeof(window.editAddressData) !== 'undefined') {
            loadAddress = window.editAddressData;
            loadAddress.address = loadAddress.areas.replace(/ /ig, '') + ' ' + loadAddress.address
        } else {
            var id = modal.getCookie('id');
            var mobile = modal.getCookie('mobile');
            var realname = decodeURIComponent(modal.getCookie('realname'));
            var address = decodeURIComponent(modal.getCookie('addressd'));
            if (id > 0) {
                loadAddress = {id: id, mobile: mobile, address: address, realname: realname}
            }
        }
        if (loadAddress) {
            modal.params.addressid = loadAddress.id;
            $('#addressInfo .has-address').show();
            $('#addressInfo .no-address').hide();
            $('#addressInfo .icon-dingwei').show();
            $('#addressInfo .realname').html(loadAddress.realname);
            $('#addressInfo .mobile').html(loadAddress.mobile);
            $('#addressInfo .address').html(loadAddress.address);
            $('#addressInfo a').attr('href', core.getUrl('member/address/selector'));
            $('#addressInfo a').click(function () {
                window.orderSelectedAddressID = loadAddress.id
            })
        }
        document.cookie = "id=0";
        var loadStore = false;
        if (typeof(window.selectedStoreData) !== 'undefined') {
            loadStore = window.selectedStoreData;
            modal.params.storeid = loadStore.id;
            var aid = '#carrierInfo';
            if(modal.params.isforceverifystore==1 && modal.params.isverify==1){
                aid='#forceStoreInfo';
            }
            $(aid +' .storename').html(loadStore.storename);
            $(aid +' .realname').html(loadStore.realname);
            $(aid +'_mobile').html(loadStore.mobile);
            $(aid +' .address').html(loadStore.address);
            $(aid).find('.no-address').css("display", "none");
            $(aid).find('.has-address').css("display", "block");
            $(aid).find('.fui-list-media').css("display", "block");
            $(aid).find('.text').css("display", "block");
            $(aid).find('.title').css("display", "block");
            $(aid).find('.subtitle').css("display", "block");
        }
        FoxUI.tab({
            container: $('#carrierTab'), handlers: {
                tab1: function () {
                    $('.exchange-withoutpostage').hide();
                    $('.exchange-withpostage').show();
                    modal.params.iscarry = 0;
                    $('#addressInfo').show(), $('#carrierInfo').hide(), $('#memberInfo').hide(), $('#showdispatchprice').show();
                    modal.caculate();
                }, tab2: function () {
                    modal.params.iscarry = 1;
                    $('.exchange-withpostage').hide();
                    $('.exchange-withoutpostage').show();
                    $('#addressInfo').hide(), $('#carrierInfo').show(), $('#memberInfo').show(), $('#showdispatchprice').hide();
                    modal.caculate();
                }
            }
        });
        var number = $('.fui-number');
        if (number.length > 0) {
            var maxbuy = number.data('maxbuy') || 0, goodsid = number.data('goodsid'),
                minbuy = number.data('minbuy') || 0, unit = number.data('unit') || '件';
            number.numbers({
                max: maxbuy,
                min: minbuy,
                minToast: "{min}" + unit + "起售",
                maxToast: "最多购买{max}" + unit,
                callback: function (num) {
                    $.each(modal.params.goods, function () {
                        if (this.goodsid == goodsid) {
                            this.total = num;
                            return false
                        }
                    });
                    $.each(modal.params.coupon_goods, function () {
                        if (this.goodsid == goodsid) {
                            this.total = num;
                            return false
                        }
                    });

                    modal.params.contype = 0;
                    modal.params.couponid = 0;
                    modal.params.wxid = 0;
                    modal.params.wxcardid = "";
                    modal.params.wxcode = "";
                    modal.params.couponmerchid = 0;
                    $('#coupondiv').find('.fui-cell-label').html('优惠券');
                    $('#coupondiv').find('.fui-cell-info').html('');
                    $('#goodscount').html(num);
                    var marketprice = core.getNumber(number.closest('.goods-item').find('.marketprice').html()) * num;
                    $('.goodsprice').html(core.number_format(marketprice, 2));
                    modal.caculate();
                }
            })
        }
        $('#deductcredit').click(function () {
            modal.calcCouponPrice();
            modal.calcCardPrice();
        });
        $('#deductcredit2').click(function () {
            modal.calcCouponPrice();
            modal.calcCardPrice();
        });
        modal.bindCoupon();
        modal.bindCard();
        $(document).click(function () {
            $('input,select,textarea').each(function () {
                $(this).attr('data-value', $(this).val());
            });
            $(':checkbox,:radio').each(function () {
                $(this).attr('data-checked', $(this).prop('checked'));
            })
        });
        $('input,select,textarea').each(function () {
            var value = $(this).attr('data-value') || '';
            if (value != '') {
                $(this).val(value);
            }
        });
        $(':checkbox,:radio').each(function () {
            var value = $(this).attr('data-checked') === 'true' ? true : false;
            $(this).prop('checked', value);
        });
        $('.buybtn').click(function () {
            modal.submit(this, params.token);
        });
        modal.caculate();
        $(".fui-cell-giftclick").click(function () {
            modal.giftPicker = new FoxUIModal({
                content: $('#gift-picker-modal').html(),
                extraClass: 'picker-modal',
                maskClick: function () {
                    modal.giftPicker.close();
                }
            });
            modal.giftPicker.container.find('.btn-danger').click(function () {
                modal.giftPicker.close();
            });
            modal.giftPicker.show();
            var giftid = $("#giftid").val();
            $(".gift-item").each(function () {
                if ($(this).val() == giftid) {
                    $(this).prop("checked", "true");
                }
            });
            $(".gift-item").on("click", function () {
                $.ajax({
                    url: core.getUrl('goods/detail/querygift', {id: $(this).val()}),
                    cache: true,
                    success: function (data) {
                        data = window.JSON.parse(data);
                        if (data.status > 0) {
                            $("#giftid").val(data.result.id);
                            $("#gifttitle").text(data.result.title);
                        }
                    }
                })
            })
        });
        $('.show-allshop-btn').click(function () {
            $(this).closest('.store-container').addClass('open');
        });
        modal.initaddress();

        $('.card-list-modal').click(function () {
            $('.card-list-modal').removeClass('in');
            $('.card-list-group').removeClass('in');
        })
        // $('#selectCard').click(function () {
        //     $('.card-list-modal').addClass('in')
        //     $('.card-list-group').addClass('in')
        // })
    };
    modal.getCookie = function (cname) {
        var name = cname + "=";
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') c = c.substring(1);
            if (c.indexOf(name) != -1) {
                return c.substring(name.length, c.length);
            }
        }
        return ""
    };
    modal.giftPicker = function () {
        modal.giftPicker = new FoxUIModal({
            content: $('#option-picker-modal').html(),
            extraClass: 'picker-modal',
            maskClick: function () {
                modal.packagePicker.close();
            }
        })
    };

    modal.bindCard = function () {
        $('#selectCard').unbind('click').click(function () {
            $('#cardloading').show();
            $("#showdispatchprice div:first-child").text("同城运费");
            core.json('membercard/query', {
                money: 0,
                type: 0,
                goods: modal.params.goods,
                discountprice: modal.params.discountprice,
                isdiscountprice: modal.params.isdiscountprice,
            }, function (rjson) {
                $('#cardloading').hide();
                if (rjson.result.cards.length > 0 || rjson.result.wxcards.length > 0) {
                    $('#selectCard').show().find('.badge').html(rjson.result.cards.length).show();
                    $('#selectCard').find('.text-danger').hide();
                    require(['../addons/ewei_shopv2/plugin/membercard/static/js/picker.js'], function (picker) {
                        picker.show({
                            card_id: modal.params.card_id,
                            cards: rjson.result.cards,
                            onCancel: function () {
                                modal.params.card_id = 0;
                                $('#selectCard').find('.fui-cell-label').html('不使用会员卡');
                                $('#selectCard').find('.fui-cell-info').html('');
                               modal.calcCardPrice();
                            },
                            onCaculate: function () {
                                modal.params.card_id = 0;
                                modal.caculate();
                            },
                            onSelected: function (data) {
                                modal.params.card_id = data.card_id;
                                modal.params.card_price = data.card_price;
                                $('#selectCard').find('.fui-cell-label').html('已选择');
                                $('#selectCard').find('.fui-cell-info').html(data.card_name);
                                $('#selectCard').data(data);
                              modal.calcCardPrice();
                            }
                        })
                    })
                } else {
                    FoxUI.toast.show('未找到会员卡!');
                    modal.hideCard();
                }
            }, false, true)
        })
    };
    modal.hideCard = function () {
        $('#selectCard').hide();
        $('#selectCard').find('.badge').html('0').hide();
        $('#selectCard').find('.text').show();
    };
    modal.hideCoupon = function () {
        $('#coupondiv').hide();
        $('#coupondiv').find('.badge').html('0').hide();
        $('#coupondiv').find('.text').show();
    };
    modal.caculate = function () {
        var goodsprice = core.getNumber($('.goodsprice').html());
        var taskdiscountprice = core.getNumber($(".taskdiscountprice").val());
        var lotterydiscountprice = core.getNumber($(".lotterydiscountprice").val());
        var discountprice = core.getNumber($(".discountprice").val());
        var isdiscountprice = core.getNumber($(".isdiscountprice").val());
        var taskcut = core.getNumber($("#taskcut").val());
        var totalprice = goodsprice - taskdiscountprice - lotterydiscountprice - discountprice - isdiscountprice - taskcut;
        if ($('.shownum').length > 0) {
            totalprice = core.getNumber($('.marketprice').html()) * parseInt($('.shownum').val());
        }
        if (modal.params.fromcart == 0) {
            if (modal.params.goods.length == 1) {
                modal.params.goods[0].total = parseInt($('.shownum').val());
            }
        }
        if (typeof(window.selectedAddressData) !== 'undefined') {
            modal.params.addressid = window.selectedAddressData.id;
        }
        core.json('order/create/caculate', {
            totalprice: totalprice,
            addressid: modal.params.addressid,
            dispatchid: modal.params.dispatchid,
            dflag: modal.params.iscarry,
            goods: modal.params.goods,
            packageid: modal.params.card_packageid,
            liveid: modal.params.liveid,
            card_id: modal.params.card_id,

        }, function (getjson) {
            if (getjson.status == 1) {
                if (modal.params.iscarry) {
                    $('.dispatchprice').html('0.00');
                } else {
                    $('.dispatchprice').html(core.number_format(getjson.result.price, 2));
                }
                if (getjson.result.taskdiscountprice) {
                    $('#taskdiscountprice').val(core.number_format(getjson.result.taskdiscountprice, 2));
                }
                if (getjson.result.lotterydiscountprice) {
                    $('#lotterydiscountprice').val(core.number_format(getjson.result.lotterydiscountprice, 2));
                }
                if (getjson.result.discountprice) {
                    $('#discountprice').val(core.number_format(getjson.result.discountprice, 2));
                }
                if (getjson.result.buyagain) {
                    $('#buyagain').val(core.number_format(getjson.result.buyagain, 2));
                    $('#showbuyagainprice').html(core.number_format(getjson.result.buyagain, 2)).parents(".fui-cell").show();
                }
                if (getjson.result.isdiscountprice) {
                    $('#isdiscountprice').val(core.number_format(getjson.result.isdiscountprice, 2));
                }
                if (getjson.result.deductcredit) {
                    $('#deductcredit_money').html(core.number_format(getjson.result.deductmoney, 2));
                    $('#deductcredit_info').html(getjson.result.deductcredit);
                    $("#deductcredit").data('credit', getjson.result.deductcredit);
                    $("#deductcredit").data('money', core.number_format(getjson.result.deductmoney, 2));
                }
                if (getjson.result.deductcredit2) {
                    $('#deductcredit2_money').html(getjson.result.deductcredit2);
                    $("#deductcredit2").data('credit2', getjson.result.deductcredit2);
                }
                if (getjson.result.include_dispath > 0) {
                    $('#include_dispath').show();
                } else {
                    $('#include_dispath').hide();
                }
                if (getjson.result.seckillprice > 0) {
                    $('#seckillprice').show();
                    $('#seckillprice_money').html(core.number_format(getjson.result.seckillprice, 2))
                } else {
                    $('#seckillprice').hide();
                    $('#seckillprice_money').html(0)
                }
                if (getjson.result.couponcount > 0) {
                    $('#coupondiv').show().find('.badge').html(getjson.result.couponcount).show();
                    $('#coupondiv').find('.text').hide();
                } else {
                    modal.params.couponid = 0;
                    $('#coupondiv').hide().find('.badge').html(0).hide();
                }
                if (getjson.result.merch_deductenough_money > 0) {
                    $('#merch_deductenough').show();
                    $('#merch_deductenough_money').html(core.number_format(getjson.result.merch_deductenough_money, 2));
                    $('#merch_deductenough_enough').html(core.number_format(getjson.result.merch_deductenough_enough, 2));
                } else {
                    $('#merch_deductenough').hide();
                    $('#merch_deductenough_money').html('0.00');
                    $('#merch_deductenough_enough').html('0.00');
                }
                if (getjson.result.deductenough_money > 0) {
                    $('#deductenough').show();
                    $('#deductenough_money').html(core.number_format(getjson.result.deductenough_money, 2));
                    $('#deductenough_enoughdeduct').html(core.number_format(getjson.result.deductenough_money, 2));
                    $('#deductenough_enough').html(core.number_format(getjson.result.deductenough_enough, 2));
                } else {
                    $('#deductenough').hide();
                    $('#deductenough_money').html('0.00');
                    $('#deductenough_enoughdeduct').html('0.00');
                    $('#deductenough_enough').html('0.00');
                }

                if (getjson.result.merchs) {
                    modal.params.merchs = getjson.result.merchs;
                }
                if (getjson.result.isnodispatch == 1) {
                    modal.isnodispatch = 1;
                    modal.nodispatch = getjson.result.nodispatch;
                    FoxUI.toast.show(modal.nodispatch);
                } else {
                    modal.isnodispatch = 0;
                    modal.nodispatch = '';
                }
                modal.params.city_express_state=getjson.result.city_express_state;

                if(modal.params.city_express_state){
                    $('.fui-cell-group.city_express.external').show();
                    $("#showdispatchprice div:first-child").text("同城运费");
                }else{
                    $('.fui-cell-group.city_express.external').hide();
                    $("#showdispatchprice div:first-child").text("运费");
                }
                if(modal.params.liveid>0&&modal.params.card_id==0){
                    $('.goodsprice').html(core.number_format(getjson.result.realprice-getjson.result.price, 2));
                    $('.marketprice').html(core.number_format(getjson.result.realprice-getjson.result.price, 2));
                }
                modal.calcCouponPrice();
                modal.calcCardPrice();
            }
        }, true, true)
    };
    modal.totalPrice = function (couponprice) {
        var goodsprice = core.getNumber($('.goodsprice').html());
        if(modal.params.packageid>0||modal.params.card_packageid>0){
            goodsprice =core.getNumber($('.bigprice-packageprice').html());
            $('#showpackageprice').show();
        }else{
            $('#showpackageprice').hide();
        }

        var taskdiscountprice = core.getNumber($(".showtaskdiscountprice").html());
        var lotterydiscountprice = core.getNumber($(".showlotterydiscountprice").html());
        var discountprice = core.getNumber($(".showdiscountprice").html());
        var isdiscountprice = core.getNumber($(".showisdiscountprice").html());
        var buyagainprice = core.getNumber($("#buyagain").val());

        var totalprice = goodsprice - taskdiscountprice - lotterydiscountprice - discountprice - isdiscountprice - couponprice - buyagainprice;
        var taskcut = core.getNumber($("#taskcut").val());
        var dispatchprice = core.getNumber($(".dispatchprice").html());

        var deductenough_enough = core.getNumber($("#deductenough_enough").html());
        var merch_deductenough_enough = core.getNumber($("#merch_deductenough_enough").html());
        var   merch_enoughprice = core.getNumber($("#merch_deductenough_money").html());
        var   enoughprice = core.getNumber($("#deductenough_money").html());

        var merch_enoughprice = 0;
        if ($("#merch_deductenough_money").length > 0 && $("#merch_deductenough_money").html() != '') {
            merch_enoughprice = core.getNumber($("#merch_deductenough_money").html());
        }
        var enoughprice = 0;
        if ($("#deductenough_money").length > 0 && $("#deductenough_money").html() != '') {
            enoughprice = core.getNumber($("#deductenough_money").html());
        }


        var packageprice = 0;
        if(modal.params.card_packageid>0&&modal.params.card_id==0){
            packageprice=core.getNumber($('.packageprice').html());

            totalprice=totalprice-packageprice;
        }

        totalprice = totalprice - merch_enoughprice - enoughprice + dispatchprice - taskcut;
        //  totalprice = totalprice + dispatchprice - taskcut;

        var deductprice = 0;

        if ($("#deductcredit").length > 0) {
            var td = core.getNumber($("#deductcredit").data('credit'));
            // $("#deductcredit_money").html(core.number_format(td, 2));
            if ($("#deductcredit").prop('checked')) {
                deductprice = core.getNumber($("#deductcredit").data('money'));
                if ($("#deductcredit2").length > 0) {
                    var td1 = core.getNumber($("#deductcredit2").data('credit2'));
                    if (totalprice - deductprice >= 0) {
                        var td = totalprice - deductprice;
                        if (td > td1) {
                            td = td1;
                        }
                        $("#deductcredit2_money").html(core.number_format(td, 2));
                    }
                }
            } else {
                if ($("#deductcredit2").length > 0) {
                    var td = core.getNumber($("#deductcredit2").data('credit2'));
                    $("#deductcredit2_money").html(core.number_format(td, 2));
                }
            }
        }
        var deductprice2 = 0;
        if ($("#deductcredit2").length > 0) {
            if ($("#deductcredit2").prop('checked')) {
                deductprice2 = core.getNumber($("#deductcredit2_money").html());
            }
        }

        var seckillprice_money=0;
        if ($("#seckillprice_money").length > 0 && $("#seckillprice_money").html() != '') {
            seckillprice_money = core.getNumber($("#seckillprice_money").html());
        }
        totalprice = totalprice - deductprice - deductprice2-seckillprice_money;


        if (totalprice <= 0) {
            totalprice = 0;
        }


        if(modal.params.gift_price>0&&goodsprice>parseInt(modal.params.gift_price)){
            $('.giftdiv').show();
            $("#giftid").val(modal.params.giftid);
        }else{
            $('.giftdiv').hide();
            $("#giftid").val(0);
        }

        $('.totalprice').html(core.number_format(totalprice));
        if(modal.params.packageid>0||modal.params.card_packageid>0){
            $('.total-packageid').html(core.number_format(totalprice));
        }
            //修复优惠券抵扣
            if ($("#deductcredit2").length > 0) {
                var td1 = core.getNumber($("#deductcredit2").data('credit2'));
                var coupondeduct_money = core.getNumber($("#coupondeduct_money").html());
                if (totalprice<td1) {
                    // $("#deductcredit2_money").html(core.number_format(td1-coupondeduct_money, 2));
                   // $("#deductcredit2").data('credit2', core.number_format(td1-coupondeduct_money, 2));
                }
            }
            //优惠券积分抵扣
            if ($("#deductcredit").length > 0) {
                var td1 = core.getNumber($("#deductcredit").data('credit'));
                var coupondeduct_money = core.getNumber($("#coupondeduct_money").html());
                if (totalprice<td1) {
                    // $("#deductcredit_money").html(core.number_format(totalprice-coupondeduct_money, 2));
                }
            }

        // if(modal.params.card_packageid>0&&modal.params.card_id==0){
        //     var goodsprice = core.getNumber($('.goodsprice').html());
        //     var packageprice = core.getNumber($('.packageprice').html());
        //     $('.total-packageid').html(core.number_format(goodsprice-packageprice));
        // }
        modal.bindCoupon();
        modal.bindCard();
        return totalprice
    };
    modal.calcCouponPrice = function () {
        var goodsprice = core.getNumber($('.goodsprice').html());
        var deductprice = 0;
        var taskdiscountprice = core.getNumber($(".taskdiscountprice").val());
        var taskcut = core.getNumber($("#taskcut").val());
        var lotterydiscountprice = core.getNumber($(".lotterydiscountprice").val());
        var discountprice = core.getNumber($(".discountprice").val());
        var isdiscountprice = core.getNumber($(".isdiscountprice").val());
        var carddeductprice = core.getNumber($("#carddeduct_money").html());
        if (modal.params.couponid == 0 && modal.params.wxid == 0) {
            $('#coupondeduct_div').hide();
            $('#coupondeduct_text').html('');
            $('#coupondeduct_money').html('0');
            if (taskdiscountprice > 0) {
                $(".showtaskdiscountprice").html($(".taskdiscountprice").val());
                $('.istaskdiscount').show();
            } else {
                $('.istaskdiscount').hide();
            }
            if (taskcut > 0) {
                $(".showtaskcut").html($("#taskcut").val());
                $('.taskcut').show();
            } else {
                $('.istaskdiscount').hide();
            }
            if (lotterydiscountprice > 0) {
                $(".showlotterydiscountprice").html($(".lotterydiscountprice").val());
                $('.islotterydiscount').show();
            } else {
                $('.islotterydiscount').hide();
            }
            if (discountprice > 0) {
                $(".showdiscountprice").html($(".discountprice").val());
                $('.discount').show();
            } else {
                $('.discount').hide();
            }
            if (isdiscountprice > 0) {
                $(".showisdiscountprice").html($(".isdiscountprice").val());
                $('.isdiscount').show();
            } else {
                $('.isdiscount').hide();
            }
            if (carddeductprice > 0) {
                $('#carddeduct_div').show();
            } else {
                $('#carddeduct_div').hide();
            }
            if(carddeductprice>0){
                return modal.totalPrice(carddeductprice);
            }else{
                return modal.totalPrice(0);
            }

        }
        core.json('order/create/getcouponprice', {
            goods: modal.params.coupon_goods,
            goodsprice: goodsprice,
            real_price:modal.realPrice(),
            couponid: modal.params.couponid,
            contype: modal.params.contype,
            wxid: modal.params.wxid,
            wxcardid: modal.params.wxcardid,
            wxcode: modal.params.wxcode,
            discountprice: discountprice,
            isdiscountprice: isdiscountprice

        }, function (getjson) {
            if (getjson.status == 1) {
                $('#coupondeduct_text').html(getjson.result.coupondeduct_text);
                deductprice = getjson.result.deductprice;
                var discountpricenew = getjson.result.discountprice;
                var isdiscountpricenew = getjson.result.isdiscountprice;
                if (discountpricenew > 0) {
                    $(".showdiscountprice").html(discountpricenew);
                    $('.discount').show();
                } else {
                    $(".showdiscountprice").html(0);
                    $('.discount').hide();
                }
                if (isdiscountpricenew > 0) {
                    $(".showisdiscountprice").html(isdiscountpricenew);
                    $('.isdiscount').show();
                } else {
                    $(".showisdiscountprice").html(0);
                    $('.isdiscount').hide();
                }
                if (deductprice > 0) {
                    $('#coupondeduct_div').show();
                    $('#coupondeduct_money').html(core.number_format(deductprice, 2))
                }else{
                    $('#coupondeduct_div').hide();
                    $('#coupondeduct_text').html('');
                    $('#coupondeduct_money').html('0');
                }

            } else {
                if (discountprice > 0) {
                    $(".showdiscountprice").html($(".discountprice").val());
                    $('.discount').show();
                } else {
                    $('.discount').hide();
                }
                if (isdiscountprice > 0) {
                    $(".showisdiscountprice").html($(".isdiscountprice").val());
                    $('.isdiscount').show();
                } else {
                    $('.isdiscount').hide();
                }
                deductprice = 0;
            }
            if(carddeductprice>0){
                return modal.totalPrice(carddeductprice+deductprice);
            }else{
                return modal.totalPrice(deductprice);
            }

        }, true, true)
    };

    modal.calcCardPrice = function () {
        if(modal.params.show_card){
            var goodsprice = core.getNumber($('.goodsprice').html());
            if(modal.params.packageid>0){
                goodsprice=core.getNumber($('.bigprice-packageprice').html());
            }
            $('#carddeduct_div').hide();
            $('#carddeduct_text').html('');
            $('#carddeduct_money').html('0');
            var carddeductprice = 0;
            var taskdiscountprice = core.getNumber($(".taskdiscountprice").val());
            var taskcut = core.getNumber($("#taskcut").val());
            var lotterydiscountprice = core.getNumber($(".lotterydiscountprice").val());
            var discountprice = core.getNumber($(".discountprice").val());
            var isdiscountprice = core.getNumber($(".isdiscountprice").val());
            var deductprice = core.getNumber($("#coupondeduct_money").html());
            var deductenough_enough = core.getNumber($("#deductenough_enough").html());
            var merch_deductenough_enough = core.getNumber($("#merch_deductenough_enough").html());
            var cardname='';

            if (modal.params.card_id == 0) {
                if(modal.params.card_packageid>0){
                    $('#showpackageprice').show();
                }else{
                    $('#showpackageprice').hide();
                }

                $('#taskcut').val(modal.params.taskcut);
                $(".showtaskcut").html(modal.params.taskcut);
                if(modal.params.taskcut>0){
                    modal.params.discountprice=0;
                    modal.params.isdiscountprice=0;
                    modal.params.deductenough_enough=0;
                    modal.params.merch_deductenough_enough=0;
                    $('#deductenough_enough').html('0.00');
                    $('#merch_deductenough_enough').html('0.00');
                    $('#deductenough_money').html('0.00');
                    $('#merch_deductenough_money').html('0.00');

                    $('#deductenough').hide();
                    $('#merch_deductenough').hide();
                }
                if(modal.params.lotterydiscountprice>0){
                    modal.params.discountprice=0;
                    modal.params.isdiscountprice=0;
                }

                if(modal.params.card_packageid>0){
                    modal.params.discountprice=0;
                    modal.params.isdiscountprice=0;
                    $('#showdiscountprice').html('0');
                    $('#discountprice').attr('value','0');
                    $('.discount').hide();
                    $('#showisdiscountprice').html('0');
                    $('#isdiscountprice').attr('value','0');
                    $('.isdiscount').hide();
                    $('#deductenough').hide();
                    $('#deductenough_money').html('0.00');
                    $('#deductenough_enoughdeduct').html('0.00');
                    $('#deductenough_enough').html('0.00');
                    $('#merch_deductenough').hide();
                    $('#merch_deductenough_money').html('0.00');
                    $('#merch_deductenough_enough').html('0.00');
                }
                if(modal.params.liveid>0){
                    modal.params.discountprice=0;
                    modal.params.isdiscountprice=0;
                }


                if(modal.params.lotterydiscountprice>0){
                    $('#lotterydiscountprice').val(modal.params.lotterydiscountprice);
                }
                if(modal.params.lotterydiscountprice>0){
                    $(".showlotterydiscountprice").html(modal.params.lotterydiscountprice);
                }


                if(core.getNumber($(".dispatchprice").html())<=0){
                    if (modal.params.iscarry) {
                        $('.dispatchprice').html('0.00');
                    }else{
                        $('.dispatchprice').html(core.number_format(modal.params.dispatch_price, 2));
                    }

                }

                if(modal.params.discountprice>0){
                    $('#discountprice').attr('value',modal.params.discountprice);
                    $(".showdiscountprice").html(modal.params.discountprice);
                }
                if(modal.params.isdiscountprice>0){
                    $('#isdiscountprice').attr('value',modal.params.isdiscountprice);
                    $(".showisdiscountprice").html(modal.params.isdiscountprice);
                }
                if (discountprice > 0||modal.params.discountprice>0) {
                    $('.discount').show();
                } else {
                    $('.discount').hide();
                }
                if (isdiscountprice > 0||modal.params.isdiscountprice>0) {
                    $('.isdiscount').show();
                } else {
                    $('.isdiscount').hide();
                }
                if(modal.params.city_express_state){
                    $('.fui-cell-group.city_express.external').show();
                    $("#showdispatchprice div:first-child").text("同城运费");
                }else{
                    $('.fui-cell-group.city_express.external').hide();
                    $("#showdispatchprice div:first-child").text("运费");
                }

                if(deductprice){
                    return modal.totalPrice(deductprice);
                }else{
                    return modal.totalPrice(0);
                }

            }
            core.json('order/create/getcardprice', {
                goods: modal.params.goods,
                goodsprice: goodsprice,
                card_id: modal.params.card_id,
                liveid: modal.params.liveid,
                card_price: modal.params.card_price,
                deductenough_enough: modal.params.deductenough_enough,
                merch_deductenough_enough: modal.params.merch_deductenough_enough,
                dispatch_price:modal.params.dispatch_price,
                lotterydiscountprice:modal.params.lotterydiscountprice,
                taskcut:modal.params.taskcut,
                discountprice: modal.params.discountprice,
                isdiscountprice: modal.params.isdiscountprice,
            }, function (getjson) {
                if (getjson.status == 1) {
                    $('#discountprice').attr('value',getjson.result.discountprice);
                    $('#isdiscountprice').attr('value',getjson.result.isdiscountprice);
                    var discountpricenew = getjson.result.discountprice;
                    var isdiscountpricenew = getjson.result.isdiscountprice;
                    if (discountpricenew > 0) {
                        $(".showdiscountprice").html(discountpricenew);
                        $('.discount').show();
                    } else {
                        $(".showdiscountprice").html(0);
                        $('.discount').hide();
                    }
                    if (isdiscountpricenew > 0) {
                        $(".showisdiscountprice").html(isdiscountpricenew);
                        $('.isdiscount').show();
                    } else {
                        $(".showisdiscountprice").html(0);
                        $('.isdiscount').hide();
                    }
                    $('#carddeduct_text').html(getjson.result.carddeduct_text);
                    carddeductprice = getjson.result.carddeductprice;
                    cardname=getjson.result.cardname;
                    $('#selectCard').find('.fui-cell-label').html('已选择');
                    $('#selectCard').find('.fui-cell-info').html(cardname);
                    $('.islotterydiscount').hide();
                    var card_dispatch_price = getjson.result.dispatch_price;
                    if(modal.params.iscarry==1 && card_dispatch_price>0){
                        card_dispatch_price = 0;
                    }
                    $('.dispatchprice').html(core.number_format(card_dispatch_price, 2));
                    if (carddeductprice > 0) {
                        $('#carddeduct_div').show();
                        $('#carddeduct_money').html(core.number_format(carddeductprice, 2));
                    }
                    $('#taskcut').val(core.number_format(getjson.result.taskcut, 2));
                    $(".showtaskcut").html(core.number_format(getjson.result.taskcut, 2));

                    if(getjson.result.taskcut>0){
                        $(".taskcut").show();
                    }else{
                        $(".taskcut").hide();
                    }

                    $('#lotterydiscountprice').val(core.number_format(getjson.result.lotterydiscountprice, 2));
                    $(".showlotterydiscountprice").html(core.number_format(getjson.result.lotterydiscountprice, 2));
                    if (getjson.result.deductcredit) {
                        $('#deductcredit_money').html(core.number_format(getjson.result.deductmoney, 2));
                        $('#deductcredit_info').html(getjson.result.deductcredit);
                        $("#deductcredit").data('credit', getjson.result.deductcredit);
                        $("#deductcredit").data('money', core.number_format(getjson.result.deductmoney, 2));
                    }
                    if (getjson.result.deductcredit2) {
                        $('#deductcredit2_money').html(getjson.result.deductcredit2);
                        $("#deductcredit2").data('credit2', getjson.result.deductcredit2);
                    }

                    if(getjson.result.dispatch_price==0&&getjson.result.shipping==1){
                        $("#showdispatchprice div:first-child").html("运费(<span style='color: red'>会员卡包邮</span>)");
                    }else {
                        if(modal.params.city_express_state){
                            $('.fui-cell-group.city_express.external').show();
                            $("#showdispatchprice div:first-child").text("同城运费");
                        }else{
                            $('.fui-cell-group.city_express.external').hide();
                            $("#showdispatchprice div:first-child").text("运费");
                        }
                    }

                    var  packageprice=core.getNumber($('.packageprice').html());
                    if((modal.params.card_packageid>0&&packageprice>0)||(packageprice<=0)){
                        $('#showpackageprice').hide();
                    }else{
                        $('#showpackageprice').show();
                    }
                    if (getjson.result.deductenough_money > 0) {
                        $('#deductenough').show();
                        $('#deductenough_money').html(core.number_format(getjson.result.deductenough_money, 2));
                        $('#deductenough_enoughdeduct').html(core.number_format(getjson.result.deductenough_money, 2));
                        $('#deductenough_enough').html(core.number_format(getjson.result.deductenough_enough, 2));
                    } else {
                        $('#deductenough').hide();
                        $('#deductenough_money').html('0.00');
                        $('#deductenough_enoughdeduct').html('0.00');
                        $('#deductenough_enough').html('0.00');
                    }


                    if(getjson.result.totalprice<merch_deductenough_enough){
                        $('#merch_deductenough').hide();
                        $('#merch_deductenough_money').html('0.00');
                        $('#merch_deductenough_enough').html('0.00');
                    }
                    if(modal.params.liveid>0){
                        $('.goodsprice').html(core.number_format(getjson.result.goodsprice, 2));
                        $('.marketprice').html(core.number_format(getjson.result.goodsprice, 2));
                    }

                } else {
                    if (discountprice > 0) {
                        $(".showdiscountprice").html($(".discountprice").val());
                        $('.discount').show();
                    } else {
                        $('.discount').hide();
                    }
                    if (isdiscountprice > 0) {
                        $(".showisdiscountprice").html($(".isdiscountprice").val());
                        $('.isdiscount').show();
                    } else {
                        $('.isdiscount').hide();
                    }
                    carddeductprice = 0
                }
                //切换使用会员卡时，优惠券折扣金额不变问题
                if(modal.params.couponid>0){
                    modal.calcCouponPrice();
                }
                if(deductprice>0){
                    return modal.totalPrice(carddeductprice+deductprice);
                }else{
                    return modal.totalPrice(carddeductprice);
                }


            }, true, true)
        }

    };
    modal.submit = function (obj, token) {
        var $this = $(obj);
        var giftid = parseInt($("#giftid").val());
        if (modal.params.mustbind) {

            /*FoxUI.alert("请先绑定手机", "提示", function () {
                location.href = core.getUrl('member/bind', {backurl: btoa(location.href)})
            });
            return*/
        }
        if ($this.attr('stop')) {
            return
        }
        if(modal.params.iscarry || modal.params.isverify || modal.params.isvirtual || modal.params.isonlyverifygoods){

            if ($(':input[name=carrier_realname]').isEmpty() && $(':input[name=carrier_realname]').attr("data-set") == 0) {
                FoxUI.toast.show('请填写联系人');
                return
            }
            if ($(':input[name=carrier_mobile]').isEmpty() && $(':input[name=carrier_mobile]').attr("data-set") == 0) {
                FoxUI.toast.show('请填写联系电话');
                return
            }
            if (!$(':input[name=carrier_mobile]').isMobile() && $(':input[name=carrier_mobile]').attr("data-set") == 0) {
                //FoxUI.toast.show('联系电话格式有误');
                FoxUI.toast.show('联系电话需请填写11位手机号');
                return
            }

        }
        if (modal.params.isonlyverifygoods) {


            if ( modal.params.isforceverifystore == 1 && modal.params.storeid == 0 ) {
                FoxUI.toast.show('请选择核销门店');
                return;
            }
        } else if (modal.params.iscarry || modal.params.isverify || modal.params.isvirtual) {

            if (modal.params.iscarry && modal.params.storeid == 0) {
                FoxUI.toast.show('请选择自提点');
                return;
            }

            if ( modal.params.isforceverifystore == 1 && modal.params.storeid == 0 ) {
                FoxUI.toast.show('请选择核销门店');
                return;
            }

        } else {
            if (modal.params.addressid == 0) {
                FoxUI.toast.show('请选择收货地址');
                return;
            } else {
                if (modal.isnodispatch == 1) {
                    FoxUI.toast.show(modal.nodispatch);
                    return;
                }
            }
        }
        var diyformdata = '';
        //if (modal.params.orderdiyformid != '0') {
        if (modal.params.has_fields) {
            var diyformdata = diyform.getData('.diyform-container');
            if (!diyformdata) {
                return
            }
        }
        if (modal.params.fromcart == 0) {
            if (modal.params.goods.length == 1) {
                modal.params.goods[0].total = parseInt($('.shownum').val())
            }
        }
        $this.attr('stop', 1);
        var data = {
            'orderid': modal.params.orderid,
            'id': modal.params.id,
            'goods': modal.params.goods,
            'card_id': modal.params.card_id,
            'giftid': giftid,
            'gdid': modal.params.gdid,
            'liveid': modal.params.liveid,
            'diydata': diyformdata,
            'dispatchtype': modal.params.iscarry ? 1 : 0,
            'fromcart': modal.params.fromcart,
            'carrierid': modal.params.iscarry ? modal.params.storeid : 0,
            'addressid': !modal.params.iscarry ? modal.params.addressid : 0,
            'carriers': (modal.params.iscarry || modal.params.isvirtual || modal.params.isverify) ? {
                'carrier_realname': $(':input[name=carrier_realname]').val(),
                'carrier_mobile': $(':input[name=carrier_mobile]').val(),
                'realname': $('#carrierInfo .realname').html(),
                'mobile': $('#carrierInfo_mobile').html(),
                'storename': $('#carrierInfo .storename').html(),
                'address': $('#carrierInfo .address').html()
            } : '',
            'remark': $("#remark").val(),
            'officcode': $("#officcode").val(),
            'deduct': ($("#deductcredit").length > 0 && $("#deductcredit").prop('checked')) ? 1 : 0,
            'deduct2': ($("#deductcredit2").length > 0 && $("#deductcredit2").prop('checked')) ? 1 : 0,
            'contype': modal.params.contype,
            'couponid': modal.params.couponid,
            'wxid': modal.params.wxid,
            'wxcardid': modal.params.wxcardid,
            'wxcode': modal.params.wxcode,
            'invoicename': $('#invoicename').val(),
            'submit': true,
            'real_price':modal.realPrice(),
            'token': token,
            'packageid': modal.params.card_packageid,
            'fromquick': modal.params.fromquick,
        };

        if(modal.params.isverify && modal.params.isforceverifystore){
            data.carrierid = modal.params.storeid;
        }

        FoxUI.loader.show('mini');
        core.json('order/create/submit', data, function (ret) {
            $this.removeAttr('stop', 1);
            if (ret.status == 0) {
                FoxUI.loader.hide();
                FoxUI.toast.show(ret.result.message);
                return
            }
            if (ret.status == -1) {
                FoxUI.loader.hide();
                FoxUI.alert(ret.result.message);
                return
            }
            if (ret.status == 3) {

                FoxUI.loader.hide();
                modal.endtime = ret.result.endtime || 0;
                modal.imgcode = ret.result.imgcode || 0;
                require(['biz/member/account'], function (account) {
                    account.initQuick({
                        action: 'bind',
                        backurl: btoa(location.href),
                        endtime: modal.endtime,
                        imgcode: modal.imgcode,
                        success: function () {
                            var args = modal.params;
                            args.refresh = true;
                            modal.open(args)
                        }
                    })
                });

                return
            }
            var payurl = core.getUrl('order/pay', {id: ret.result.orderid});
            if(core.options && core.options.siteUrl){
                payurl = core.options.siteUrl+ 'app' + payurl.substr(1);
            }
            //location.href = core.getUrl('order/pay', {id: ret.result.orderid});
            location.href = payurl;
        }, false, true)
    };
    modal.initaddress = function (params) {
        var reqParams = ['foxui.picker'];
        if (modal.params.new_area) {
            reqParams = ['foxui.picker', 'foxui.citydatanew']
        }
        require(reqParams, function () {
            $('#areas').cityPicker({
                title: '请选择所在城市',
                new_area: modal.params.new_area,
                address_street: modal.params.address_street,

                onClose: function (self) {
                    var datavalue = $('#areas').attr('data-value');
                    var codes = datavalue.split(' ');
                    if(modal.params.new_area){
                        $('#areas').attr('data-datavalue',datavalue);
                    }

                    if (modal.params.new_area && modal.params.address_street) {
                        var city_code = codes[1];
                        var area_code = codes[2];
                        city_code = city_code + '';
                        area_code = area_code + '';
                        var data = modal.loadStreetData(city_code, area_code);
                        var street = $('<input type="text" id="street"  name="street" data-value="" value="" placeholder="所在街道"  class="fui-input" readonly=""/>');
                        var parents = $('#street').closest('.fui-cell-info');
                        $('#street').remove();
                        parents.append(street);
                        street.cityPicker({title: '请选择所在街道', street: 1, data: data,onClose: function (self) {
                            var streetdatavalue = $('#street').attr('data-value');
                            $('#street').attr('data-datavalue',streetdatavalue);
                        }})
                    }
                }
            });
            if (modal.params.new_area && modal.params.address_street) {
                var datavalue = $('#areas').attr('data-value');
                if (datavalue) {
                    var codes = datavalue.split(' ');
                    var city_code = codes[1];
                    var area_code = codes[2];
                    var data = modal.loadStreetData(city_code, area_code);
                    $('#street').cityPicker({title: '请选择所在街道', street: 1, data: data})
                }
            }
        });
        $(document).on('click', '#btn-submit', function () {
            if ($(this).attr('submit')) {
                return
            }
            if ($('#realname').isEmpty()) {
                FoxUI.toast.show("请填写收件人");
                return
            }
            var jingwai = /(境外地区)+/.test($('#areas').val());
            var taiwan = /(台湾)+/.test($('#areas').val());
            var aomen = /(澳门)+/.test($('#areas').val());
            var xianggang = /(香港)+/.test($('#areas').val());
            if (jingwai || taiwan || aomen || xianggang) {
                if ($('#mobile').isEmpty()) {
                    FoxUI.toast.show("请填写手机号码");
                    return
                }
            } else {
                if (!$('#mobile').isMobile()) {
                    FoxUI.toast.show("请填写正确手机号码");
                    return
                }
            }
            if ($('#areas').isEmpty()) {
                FoxUI.toast.show("请填写所在地区");
                return
            }
            if ($('#address').isEmpty()) {
                FoxUI.toast.show("请填写详细地址");
                return
            }
            $('#btn-submit').html('正在处理...').attr('submit', 1);
            window.editAddressData = {
                realname: $('#realname').val(),
                mobile: $('#mobile').val(),
                address: $('#address').val(),
                areas: $('#areas').val(),
                street: $('#street').val(),
                streetdatavalue: $('#street').attr('data-datavalue'),
                datavalue: $('#areas').attr('data-datavalue'),
                isdefault: $('#isdefault').is(':checked') ? 1 : 0
            };
            core.json('member/address/submit', {
                id: $('#addressid').val(),
                addressdata: window.editAddressData
            }, function (json) {
                $('#btn-submit').html('保存地址').removeAttr('submit');
                window.editAddressData.id = json.result.addressid;
                if (json.status == 1) {
                    FoxUI.toast.show('保存成功!');
                    $("#addaddress").css("display", "none");
                    location.reload()
                } else {
                    FoxUI.toast.show(json.result.message)
                }
            }, true, true)
        })
    };
    modal.loadStreetData = function (city_code, area_code) {
        var left = city_code.substring(0, 2);
        var xmlUrl = '../addons/ewei_shopv2/static/js/dist/area/list/' + left + '/' + city_code + '.xml';
        var xmlCityDoc = modal.loadXmlFile(xmlUrl);
        var CityList = xmlCityDoc.childNodes[0].getElementsByTagName("county");
        var data = [];
        if (CityList.length > 0) {
            for (var i = 0; i < CityList.length; i++) {
                var county = CityList[i];
                var county_code = county.getAttribute("code");
                if (county_code == area_code) {
                    var streetlist = county.getElementsByTagName("street");
                    for (var m = 0; m < streetlist.length; m++) {
                        var street = streetlist[m];
                        data.push({
                            "text": street.getAttribute('name'),
                            "value": street.getAttribute('code'),
                            "children": []
                        })
                    }
                }
            }
        }
        return data
    };


    modal.bindCoupon = function () {
        $('#coupondiv').unbind('click').click(function () {
            if( $('#coupondiv').attr('is_open') ){
                return;
            }
            $('#coupondiv').attr('is_open', true);
            // 异步请求为false
            $('#couponloading').show();
            core.json('sale/coupon/util/query', {
                money: 0,
                type: 0,
                merchs: modal.params.merchs,
                goods: modal.params.goods
            }, function (rjson) {
                $('#couponloading').hide();
                if (rjson.result.coupons.length > 0 || rjson.result.wxcards.length > 0) {
                    $('#coupondiv').show().find('.badge').html(rjson.result.coupons.length + rjson.result.wxcards.length).show();
                    $('#coupondiv').find('.text').hide();
                    require(['biz/sale/coupon/picker'], function (picker) {
                        picker.show({
                            couponid: modal.params.couponid,
                            coupons: rjson.result.coupons,
                            wxcards: rjson.result.wxcards,
                            onClose:function(){
                                $('#coupondiv').removeAttr('is_open');
                            },
                            onCancel: function () {
                                modal.params.contype = 0;
                                modal.params.couponid = 0;
                                modal.params.wxid = 0;
                                modal.params.wxcardid = "";
                                modal.params.wxcode = "";
                                modal.params.couponmerchid = 0;
                                $('#coupondiv').find('.fui-cell-label').html('优惠券');
                                $('#coupondiv').find('.fui-cell-info').html('');
                                modal.calcCouponPrice();

                                //modal.caculate();
                            },
                            onSelected: function (data) {
                                modal.params.contype = data.contype;
                                if (modal.params.contype == 1) {
                                    modal.params.couponid = 0;
                                    modal.params.wxid = data.wxid;
                                    modal.params.wxcardid = data.wxcardid;
                                    modal.params.wxcode = data.wxcode;
                                    modal.params.couponmerchid = data.merchid;
                                    $('#coupondiv').find('.fui-cell-label').html('已选择');
                                    $('#coupondiv').find('.fui-cell-info').html(data.couponname);
                                    $('#coupondiv').data(data);
                                    modal.calcCouponPrice();
                                } else if (modal.params.contype == 2) {
                                    modal.params.couponid = data.couponid;
                                    modal.params.wxid = 0;
                                    modal.params.wxcardid = "";
                                    modal.params.wxcode = "";
                                    modal.params.couponmerchid = data.merchid;
                                    $('#coupondiv').find('.fui-cell-label').html('已选择');
                                    $('#coupondiv').find('.fui-cell-info').html(data.couponname);
                                    $('#coupondiv').data(data);
                                    modal.calcCouponPrice()
                                } else {
                                    modal.params.contype = 0;
                                    modal.params.couponid = 0;
                                    modal.params.wxid = 0;
                                    modal.params.wxcardid = "";
                                    modal.params.wxcode = "";
                                    modal.params.couponmerchid = 0;
                                    $('#coupondiv').find('.fui-cell-label').html('优惠券');
                                    $('#coupondiv').find('.fui-cell-info').html('');
                                    modal.calcCouponPrice()
                                }
                            }
                        })
                    })
                } else {
                    FoxUI.toast.show('未找到优惠券!');
                    modal.hideCoupon()
                }
            }, false, true)
        })
    };
    modal.hideCoupon = function () {
        $('#coupondiv').hide();
        $('#coupondiv').find('.badge').html('0').hide();
        $('#coupondiv').find('.text').show();
        $('#coupondiv').removeAttr('is_open');
    };
    modal.loadXmlFile = function (xmlFile) {
        var xmlDom = null;
        if (window.ActiveXObject) {
            xmlDom = new ActiveXObject("Microsoft.XMLDOM");
            xmlDom.async = false;
            xmlDom.load(xmlFile) || xmlDom.loadXML(xmlFile)
        } else if (document.implementation && document.implementation.createDocument) {
            var xmlhttp = new window.XMLHttpRequest();
            xmlhttp.open("GET", xmlFile, false);
            xmlhttp.send(null);
            xmlDom = xmlhttp.responseXML
        } else {
            xmlDom = null
        }
        return xmlDom
    };
    $(document).on('click','#invoicename', function () {
        var type = $(this).data('type');
        invoice.open(modal.invoice_info, function (invoice_info) {
            //纳税号和抬头绝对不允许包含空格！空格用来分隔各类数据
            modal.invoice_info = invoice_info;
            var text = '['+(modal.invoice_info.entity==true ? '纸质':'电子')+'] ';
            text += modal.invoice_info.title;
            text += ' （' + (modal.invoice_info.company==true ? '单位':'个人') + (modal.invoice_info.number?': '+modal.invoice_info.number:'') +'）';
            $("#invoicename").val(text);
        },type);
    });
    modal.realPrice = function () {
        var goodsprice = core.getNumber($('.goodsprice').html());
        var taskdiscountprice = core.getNumber($(".showtaskdiscountprice").html());
        var lotterydiscountprice = core.getNumber($(".showlotterydiscountprice").html());
        var discountprice = core.getNumber($(".showdiscountprice").html());
        var isdiscountprice = core.getNumber($(".showisdiscountprice").html());
        var buyagainprice = core.getNumber($("#buyagain").val());
        var totalprice = goodsprice - taskdiscountprice - lotterydiscountprice - discountprice - isdiscountprice - buyagainprice;
        var taskcut = core.getNumber($("#taskcut").val());
        var deductenough_enough = core.getNumber($("#deductenough_enough").html());
        var merch_deductenough_enough = core.getNumber($("#merch_deductenough_enough").html());
        var merch_enoughprice = core.getNumber($("#merch_deductenough_money").html());
        var enoughprice = core.getNumber($("#deductenough_money").html());

        var merch_enoughprice = 0;
        if ($("#merch_deductenough_money").length > 0 && $("#merch_deductenough_money").html() != '') {
            merch_enoughprice = core.getNumber($("#merch_deductenough_money").html());
        }
        var enoughprice = 0;
        if ($("#deductenough_money").length > 0 && $("#deductenough_money").html() != '') {
            enoughprice = core.getNumber($("#deductenough_money").html());
        }
        var packageprice = 0;
        if(modal.params.card_packageid>0&&modal.params.card_id==0){
            packageprice=core.getNumber($('.packageprice').html());

            totalprice=totalprice-packageprice;
        }
        var carddeduct_money=0;
        if(modal.params.show_card){
           carddeduct_money = core.getNumber($("#carddeduct_money").html());
            totalprice=totalprice-carddeduct_money;
        }

        totalprice = totalprice - merch_enoughprice - enoughprice - taskcut;
        var seckillprice_money=0;
        if ($("#seckillprice_money").length > 0 && $("#seckillprice_money").html() != '') {
            seckillprice_money = core.getNumber($("#seckillprice_money").html());
        }
        totalprice = totalprice-seckillprice_money;
        if (totalprice <= 0) {
            totalprice = 0;
        }
        return totalprice
    };
    return modal
});