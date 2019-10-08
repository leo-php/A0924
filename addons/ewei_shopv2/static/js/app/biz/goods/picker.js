define(['core', 'tpl', 'biz/member/cart', 'biz/plugin/diyform'], function (core, tpl, cart, diyform) {
    var modal = {
        goodsid: 0,
        goods: [],
        option: false,
        specs: [],
        options: [],
        params: {
            titles: '',
            optionthumb: '',
            split: ';',
            option: false,
            total: 1,
            optionid: 0,
            onSelected: false,
            onConfirm: false,
            autoClose: true
        }
    };
    modal.open = function (params) {
        modal.params = $.extend(modal.params, params || {});
        if (modal.goodsid != params.goodsid || params.refresh) {
            modal.specs = [];
            modal.options = [];
            modal.option = false;
            modal.params.optionid = 0;
            modal.goodsid = params.goodsid;
            var obj = {id: params.goodsid};
            if (params.liveid) {
                obj.liveid = params.liveid
            }
            if (params.cangift) {
                obj.cangift = params.cangift
            }

            core.json('goods/picker', obj, function (ret) {
                if (ret.status == 0) {
                    FoxUI.toast.show('未找到商品!');
                    return
                }
                modal.followtip = '';
                modal.followurl = '';
                if (ret.status == 2) {
                    modal.followtip = ret.result.followtip;
                    modal.followurl = ret.result.followurl;
                    modal.show();
                    return
                }
                if (ret.status == 4) {
                    modal.followtip = 0;
                    modal.needlogin = 1;
                    modal.endtime = ret.result.endtime || 0;
                    modal.imgcode = ret.result.imgcode || 0;
                    modal.show();
                    return
                }
                if (ret.status == 3) {
                    modal.followtip = 0;
                    modal.needlogin = 0;
                    modal.mustbind = 1;
                    modal.endtime = ret.result.endtime || 0;
                    modal.imgcode = ret.result.imgcode || 0;
                    modal.show();
                    return
                }
                if (ret.status == 5) {
                    FoxUI.toast.show(ret.result.message);
                    modal.goodsid = '';
                    return
                }
                var width = window.screen.width *  window.devicePixelRatio;
                var height = window.screen.height *  window.devicePixelRatio;
                ret.result.width = width;
                ret.result.height = height;
                modal.containerHTML = tpl('option-picker', ret.result);
                modal.goods = ret.result.goods;
                modal.specs = ret.result.specs;
                modal.options = ret.result.options;
                modal.seckillinfo = ret.result.seckillinfo;
                if (modal.goods.unit == '') {
                    modal.goods.unit = '件'
                }
                modal.needlogin = 0;
                modal.followtip = 0;
                modal.mustbind = 0;
                modal.show();
                if(params.action =='' || params.action == undefined){
                    if (ret.result.goods.giftid >0){
                        $('.cartbtn').hide();
                    } else{
                        $('.cartbtn').show();
                    }
                    $('.confirmbtn').hide();

                    $('.buybtn').show();
                }
            }, true, false)
        } else {
            modal.show();
            if(params.action == ""){
                $('.confirmbtn').hide();
                $('.cartbtn').show();
                $('.buybtn').show();
            }
        }
    };
    modal.close = function () {
        modal.container.close()
    };
    modal.init = function () {
        //修改周期购送货时间
        $('.other-time').click(function () {
            $(".cyceltime").css('display','block');
            $(".cyclenotime").css('display','none');
            $(".cancelbtn").css('display',' table-cell');
            // alert('emmm....');
        });

        //商品详情picker里自定义文本  软键盘
        var clientHeight = document.documentElement.clientHeight || document.body.clientHeight;
        $(window).on('resize', function () {
            var nowClientHeight = document.documentElement.clientHeight || document.body.clientHeight;
            if (clientHeight > nowClientHeight) {
                //键盘弹出的事件处理
                var h = document.body.clientHeight;
                $('.fui-page.fui-page-current').css('overflow', 'hidden');
                $('.fui-page.fui-page-current').css('height', h);
            }
            else {
                //键盘收起的事件处理
                var h = document.body.clientHeight;
                $('.fui-page.fui-page-current').css('overflow', 'auto');
                $('.fui-page.fui-page-current').css('height', h);
            }
        });

        $('.closebtn', modal.container.container).unbind('click').click(function () {
            modal.close()
        });
        $('.cancelbtn', modal.container.container).unbind('click').click(function () {
            $(".cyceltime").css('display','none');
            $(".cyclenotime").css('display','block');
            $(".cancelbtn").css('display',' none');
            /*modal.close()*/
        });
        $('.fui-mask').unbind('click').click(function () {
            modal.close()
        });
        if (modal.seckillinfo == false) {
            $('.fui-number', modal.container.container).numbers({
                value: modal.params.total,
                max: modal.goods.maxbuy,
                min: modal.goods.minbuy,
                minToast: "{min}" + modal.goods.unit + "起售",
                maxToast: "最多购买{max}" + modal.goods.unit,
                callback: function (num) {
                    modal.params.total = num
                }
            })
        } else {
            modal.params.total = 1
        }
        $(".spec-item", modal.container.container).unbind('click').click(function () {
            modal.chooseSpec(this)
        });
        $('.cartbtn', modal.container.container).unbind('click').click(function () {
            modal.addToCart()
        });
        $(".gift-item").on("click", function () {
            $.ajax({
                url: core.getUrl('goods/detail/querygift', {id: $(this).val()}),
                cache: true,
                success: function (data) {
                    data = window.JSON.parse(data);
                    if (data.status > 0) {
                      modal.params.giftid = data.result.id;
                      modal.params.getgift = 1;
                    }
                }
            });
        });
        $('.buybtn', modal.container.container).unbind('click').click(function () {
            if ($(this).hasClass('disabled')) {
                return
            }
            if (!modal.check()) {
                return
            }
            giftid = 0;
            //如果只有一个

            if (modal.params.cangift ==1 && (modal.goods.giftinfo.length ==1)) {
                giftid = modal.goods.giftid;
            }
            //如果有多个

            if(modal.params.cangift ==1 && modal.params.giftid == undefined && modal.goods.giftinfo.length >1) {
                FoxUI.toast.show('请选择赠品');
                return;
            }else{
                if (modal.params.getgift == undefined) {
                    giftid = modal.goods.giftid;
                }else{
                    giftid = modal.params.giftid;
                }

            }

            if ($('.diyform-container').length > 0) {
                var diyformdata = diyform.getData('.diyform-container');
                if (!diyformdata) {
                    return
                } else {
                    core.json('order/create/diyform', {id: modal.goods.id, diyformdata: diyformdata}, function (ret) {
                        location.href = core.getUrl('order/create', {
                            id: modal.goods.id,
                            optionid: modal.params.optionid,
                            giftid:giftid,
                            total: modal.params.total,
                            gdid: ret.result.goods_data_id
                        })
                    }, true, true)
                }
            } else {
                location.href = core.getUrl('order/create', {
                    id: modal.goods.id,
                    optionid: modal.params.optionid,
                    giftid:giftid,
                    total: modal.params.total
                })
            }
            if (modal.params.autoClose) {
                modal.close()
            }
        });
        $('.confirmbtn', modal.container.container).unbind('click').click(function () {
            if ($(this).hasClass('disabled')) {
                return
            }
            if (!modal.check()) {
                return
            }
            if (modal.params.onConfirm) {
                modal.params.total = parseInt($('.num', modal.container.container).val());
                modal.params.onConfirm(modal.params.total, modal.params.optionid, modal.params.titles, modal.params.optionthumb)
            }

            if (modal.params.autoClose) {
                modal.close()
            }
        });
        var height = $(document.body).height() * 0.6;
        var optionsHeight = height - $('.option-picker-cell').outerHeight() - $('.option-picker .fui-navbar').outerHeight();
        modal.container.container.find('.option-picker').css('height', height);
        //时间选择器
        $('.date-picker').css('height', '18rem');
        modal.container.container.find('.option-picker .option-picker-options').css('height', optionsHeight);
        var clientHeight = document.documentElement.clientHeight || document.body.clientHeight;
        $(window).on('resize', function () {
            var nowClientHeight = document.documentElement.clientHeight || document.body.clientHeight;
            if (clientHeight > nowClientHeight) {
                $('.fui-navbar').css({display: 'none'});
                $('.option-picker').css({height: 'auto'});
                var height = $(document.body).height() * 0.6;
                var optionsHeight = height - $('.option-picker-cell').outerHeight();
                modal.container.container.find('.option-picker').css('height', height);
                modal.container.container.find('.option-picker .option-picker-options').css('height', optionsHeight);
                $('.option-picker').addClass('android')
            } else {
                $('.fui-navbar').css({display: 'block'});
                var height = $(document.body).height() * 0.6;
                var optionsHeight = height - $('.option-picker-cell').outerHeight() - $('.option-picker .fui-navbar').outerHeight();
                modal.container.container.find('.option-picker').css('height', height);
                modal.container.container.find('.option-picker .option-picker-options').css('height', optionsHeight);
                $('.option-picker').addClass('android')
            }
        })
    };
    modal.addToCart = function () {
        if (!modal.goods.canAddCart) {
            FoxUI.toast.show('此商品不可加入购物车<br>请直接点击立刻购买');
            return
        }
        if ($(this).hasClass('disabled')) {
            return
        }
        if (!modal.check()) {
            return
        }
        modal.params.total = parseInt($('.num', modal.container.container).val());
        if ($('.diyform-container').length > 0) {
            FoxUI.loader.show('mini');
            var diyformdata = diyform.getData('.option-picker .diyform-container');
            FoxUI.loader.hide();
            if (!diyformdata) {
                return
            }
            require(['biz/member/cart'],function(cart){
                cart.add(modal.goodsid, modal.params.optionid, modal.params.total, diyformdata, function (ret) {
                    FoxUI.toast.show('添加成功');
                    modal.changeCartcount(ret.cartcount)
                });
            });
        } else {
            require(['biz/member/cart'],function(cart){
                cart.add(modal.goodsid, modal.params.optionid, modal.params.total, false, function (ret) {
                    FoxUI.toast.show('添加成功');
                    modal.changeCartcount(ret.cartcount)
                });
            });
        }
        if (modal.params.autoClose) {
            modal.close()
        }
    };
    modal.show = function () {
        if (modal.followtip) {
            FoxUI.confirm(modal.followtip, function () {
                if (modal.followurl != '' && modal.followurl != null) {
                    location.href = modal.followurl
                }
            });
            return
        }
        if (modal.needlogin) {
            var backurl = core.getUrl('goods/detail', {id: modal.goodsid});
            backurl = backurl.replace("./index.php?", "");
            require(['biz/member/account'], function (account) {
                account.initQuick({
                    action: 'login',
                    backurl: btoa(backurl),
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
        if (modal.mustbind) {
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
        modal.container = new FoxUIModal({content: modal.containerHTML, extraClass: "picker-modal"});
        modal.init();
        if (modal.seckillinfo && modal.seckillinfo.status == 0) {
            $('.fui-mask').hide(), $('.picker-modal').hide();
            if ((typeof(modal.options.length) === 'undefined' || modal.options.length <= 0) && $('.diyform-container').length <= 0) {
                if (modal.params.action == 'buy') {
                    location.href = core.getUrl('order/create', {id: modal.goods.id, total: 1, optionid: 0});
                    return
                } else if(modal.params.action == 'cart') {
                    modal.addToCart();
                    return
                }
            }
        }
        $('.fui-mask').show(), $('.picker-modal').show();
        if (modal.params.showConfirm) {
            $('.confirmbtn', modal.container.container).show()
        } else {
            $('.buybtn', modal.container.container).show();
            if (modal.goods.canAddCart) {
                $('.cartbtn', modal.container.container).show()
            }
        }
        if (modal.params.optionid != '0') {
            modal.initOption()
        }
        modal.container.show();
        if (modal.specs.length == 1) {
            $.each(modal.options, function () {
                var thisspecs = this.specs;
                if (this.stock == 0) {
                    $(".spec-item" + thisspecs + "").removeClass("spec-item").removeClass("btn-danger").addClass("disabled").off("click")
                }
            })
        }
    };
    modal.initOption = function () {
        $(".spec-item").removeClass('btn-danger');
        var optionid = modal.params.optionid;
        var specs = false;
        $.each(modal.options, function () {
            if (this.id == optionid) {
                specs = this.specs.split('_');
                return false
            }
        });
        if (specs) {
            var item = false;
            var selectitems = [];
            $(".spec-item").each(function () {
                var item = $(this), itemid = item.data('id');
                $.each(specs, function () {
                    if (this == itemid) {
                        selectitems.push(item);
                        item.addClass('btn-danger')
                    }
                })
            });
            if (selectitems.length > 0) {
                var lastitem = selectitems[selectitems.length - 1];
                modal.chooseSpec(lastitem, false)
            }
        }
    };
    modal.chooseSpec = function (obj, callback) {
        var $this = $(obj);
        if($this.hasClass('btn-danger')){
            $this.removeClass('btn-danger');
            $('.nav').removeClass('disabled').addClass('spec-item');
            $(".spec-item", modal.container.container).unbind('click').click(function () {
                modal.chooseSpec(this)
            });
        }else{
            $this.closest('.spec').find('.spec-item').removeClass('btn-danger'), $this.addClass('btn-danger');
        }

        var thumb = $this.data('thumb') || '';
        if (thumb) {
            $('.thumb', modal.container.container).attr('src', thumb)
        }
        modal.params.optionthumb = thumb;
        var selected = $(".spec-item.btn-danger", modal.container.container);

        var itemids = [];
        if (selected.length <= modal.specs.length) {
            $.each(modal.options, function () {
                if ((modal.specs.length - selected.length) == 1) {
                    var specid = [];
                    var specOpion = this.specs;
                    $.each(selected, function () {
                        if (specOpion.indexOf(this.getAttribute("data-id")) >= 0) {
                            specid.push(this.getAttribute("data-id"))
                        }
                    });

                    if (specid.length == selected.length) {
                        for (var i = 0; i < specid.length; i++) {
                            specOpion = specOpion.replace(specid[i], "")
                        }
                        specOpion = specOpion.split("_");
                        var option = [];
                        $.each(specOpion, function (i, v) {
                            var data = $.trim(v);
                            if ('' != data) {
                                option.push(data)
                            }
                        });
                        if (this.stock <= 0 && this.stock != -1) {
                            $(".spec-item" + option[0] + "").removeClass("spec-item").removeClass("btn-danger").addClass("disabled").off("click");
                        } else {
                            $(".spec-item" + option[0] + "").removeClass("disabled").addClass("spec-item").off("click").on("click", function () {
                                modal.chooseSpec(this);
                            })
                        }
                    }
                } else if (modal.specs.length == selected.length) {
                    var specid = [];
                    var specOpion = this.specs;
                    $.each(selected, function () {
                        if (specOpion.indexOf(this.getAttribute("data-id")) >= 0 && specOpion.indexOf($this.data("id")) >= 0) {
                            specid.push(this.getAttribute("data-id"))
                        }
                    });
                    var option = [];
                    if (specid.length == (modal.specs.length - 1)) {
                        for (var i = 0; i < specid.length; i++) {
                            specOpion = specOpion.replace(specid[i], "")
                        }
                        specOpion = specOpion.split("_");
                        $.each(specOpion, function (i, v) {
                            var data = $.trim(v);
                            if ('' != data) {
                                option.push(data)
                            }
                        });
                        if (this.stock <= 0 && this.stock != -1) {
                            $(".spec-item" + option[0] + "").removeClass("spec-item").removeClass("btn-danger").addClass("disabled").off("click")
                        } else {
                            $(".spec-item" + option[0] + "").removeClass("disabled").addClass("spec-item").off("click").on("click", function () {
                                modal.chooseSpec(this)
                            })
                        }
                    }
                }
            })
        }
        if (selected.length == modal.specs.length) {
            selected.each(function () {
                itemids.push($(this).data('id'))
            });
            $.each(modal.options, function () {
                var specs = this.specs.split('_').sort().join('_');
                if (specs == itemids.sort().join('_')) {
                    var stock = this.stock == '-1' ? '无限' : this.stock;
                    $('.total', modal.container.container).html(stock);
                    if (this.stock != '-1' && this.stock <= 0) {
                        $('.confirmbtn', modal.container).show().addClass('disabled').html('库存不足');
                        $('.cartbtn,.buybtn', modal.container).hide()
                    } else {
                        if (modal.params.showConfirm) {
                            $('.confirmbtn', modal.container).removeClass('disabled').html('确定');
                            $('.cartbtn,.buybtn', modal.container).hide()
                        } else {
                            $('.cartbtn,.buybtn', modal.container).show(), $('.confirmbtn').hide()
                        }
                    }
                    var timestamp = Date.parse(new Date()) / 1000;
                    if (modal.goods.ispresell > 0 && (modal.goods.preselltimeend == 0 || modal.goods.preselltimeend > timestamp)) {
                        $('.price', modal.container.container).html(this.presellprice)
                    } else {
                        $('.price', modal.container.container).html(this.marketprice)
                    }
                    if(this.seecommission>0) {
                        $('.option-Commission').addClass('show');
                        $('.option-Commission span', modal.container.container).html(this.seecommission);
                    }
                    modal.option = this;
                    modal.params.optionid = this.id
                }
            })
        }
        var titles = [];
        selected.each(function () {
            titles.push($.trim($(this).html()))
        });
        modal.params.titles = titles.join(modal.params.split);
        $('.info-titles', modal.container.container).html('已选 ' + modal.params.titles);

        if (callback) {
            if (modal.params.onSelected) {
                modal.params.onSelected(modal.params.total, modal.params.optionid, modal.params.titles)
            }
        }
    };
    modal.check = function () {
        var spec = $(".spec", modal.container.container);
        var selected = true;
        spec.each(function () {
            if ($(this).find('.spec-item.btn-danger').length <= 0) {
                FoxUI.toast.show('请选择' + $(this).find('.title').html());
                selected = false;
                return false
            }
        });
        if (selected) {
            if (modal.option.stock != -1 && modal.option.stock <= 0) {
                FoxUI.toast.show('库存不足');
                return false
            }
            var num = parseInt($('.num', modal.container.container).val());
            if (num <= 0) {
                num = 1
            }
            if (num > modal.option.stock) {
                num = modal.option.stock
            }
            $(".num", modal.container.container).val(num);
            if (modal.goods.maxbuy > 0 && num > modal.goods.maxbuy) {
                FoxUI.toast.show('最多购买 ' + modal.goods.maxbuy + ' ' + modal.goods.unit);
                return false
            }
            if (modal.goods.minbuy > 0 && num < modal.goods.minbuy) {
                FoxUI.toast.show(modal.goods.minbuy + modal.goods.unit + '起售');
                return false
            }
            return true
        }
        return false
    };
    modal.changeCartcount = function (count) {
        if ($("#menucart").length > 0) {
            var badge = $("#menucart").find(".badge");
            if (badge.length < 1) {
                $("#menucart").append('<span class="badge in">' + count + '</div>')
            } else {
                $('.cart-item').find('.badge').html(count).removeClass('out').addClass('in');
                badge.text(count)
            }
        }
    };
    return modal
});