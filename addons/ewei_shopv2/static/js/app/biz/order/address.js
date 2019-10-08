define(['core', 'tpl'], function (core, tpl) {
    var modal = {};
    modal.initPost = function (params) {
        var reqParams = ['foxui.picker'];
        if (params.new_area) {
            reqParams = ['foxui.picker', 'foxui.citydatanew']
        }
        $(document).on('click', '#btn-address', function () {
            wx.openAddress({
                success: function (res) {
                    $("#realname").val(res.userName);
                    $('#mobile').val(res.telNumber);
                    $('#address').val(res.detailInfo);
                    $('#areas').val(res.provinceName + " " + res.cityName + " " + res.countryName);
                    var provinceName = res.provinceName, cityName = res.cityName, countyName = res.countryName;
                    var province_code = 0, city_code = 0, county_code = 0;
                    var xmlfile = '../addons/ewei_shopv2/static/js/dist/area/AreaNew.xml?v=5';
                    xmlDoc = loadXmlFile(xmlfile);
                    if (window.ActiveXObject) {
                        TopnodeList = xmlDoc.selectSingleNode("address").childNodes
                    } else {
                        TopnodeList = xmlDoc.childNodes[0].getElementsByTagName("province")
                    }
                    if (TopnodeList.length > 0) {
                        for (var province_index = 0; province_index < TopnodeList.length; province_index++) {
                            province = TopnodeList[province_index];
                            if (province.getAttribute("name") == provinceName && provinceName != null && provinceName.trim().length > 0) {
                                province_code = province.getAttribute("code");
                                citys = TopnodeList[province_index].getElementsByTagName("city");
                                for (var citys_index = 0; citys_index < citys.length; citys_index++) {
                                    if (citys[citys_index].getAttribute("name") == cityName && cityName != null && cityName.trim().length > 0) {
                                        city_code = citys[citys_index].getAttribute("code");
                                        county = TopnodeList[province_index].getElementsByTagName("city")[citys_index].getElementsByTagName("county");
                                        for (var county_index = 0; county_index < county.length; county_index++) {
                                            if (county[county_index].getAttribute("name") == countyName && countyName != null && countyName.trim().length > 0) {
                                                county_code = county[county_index].getAttribute("code")
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    var data = loadStreetData(city_code, county_code);
                    $('#street').cityPicker({
                        title: '请选择所在街道', street: 1, data: data, onClose: function (self) {
                            var streetdatavalue = $('#street').attr('data-value');
                            $('#street').attr('data-datavalue', streetdatavalue);
                        }
                    });
                    $("#areas").attr("data-strvalue", province_code + " " + city_code + " " + county_code);
                    $("#btn-submit").attr("id", "btn-submitadd");
                }
            })
        });
        $(document).on('click', '#btn-submitadd', function () {
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
                datavalue: $('#areas').attr('data-strvalue'),
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
    window.loadXmlFile = function (xmlFile) {
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
    window.loadStreetData = function (city_code, area_code) {
        var left = city_code.substring(0, 2);
        var xmlUrl = '../addons/ewei_shopv2/static/js/dist/area/list/' + left + '/' + city_code + '.xml';
        var xmlCityDoc = loadXmlFile(xmlUrl);
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
    return modal
});