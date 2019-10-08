define(['core', 'tpl'], function (core, tpl) {
    var modal = {store: false};
      modal.init = function (params) {
        modal.store = params.store;
        FoxUI.loader.show('mini');

        if(modal.store.lng==null||modal.store.lat==null||modal.store.lng==""||modal.store.lat==""){
            modal.store.lng=0;
            modal.store.lat=0;
        }

        if(modal.store.storename=="" || modal.store.storename==undefined){
            modal.store.storename="未填写";
        }

        if(modal.store.address=="" || modal.store.address==undefined){
            modal.store.address="未填写";
        }

        var height = $(document.body).height() - $('.fui-header').height() - $('.fui-footer .fui-list:first-child').height() - 20;
        if (params.isios) {
            height = height - 20;
        }
        $('#js-map').height(height + 'px');
          setTimeout(function () {
              var map = new AMap.Map("js-map", {
                  resizeEnable: true,
                  center: [modal.store.lng,modal.store.lat],
                  zoom: 15
              });

        marker = new AMap.Marker({
            icon: "http://webapi.amap.com/theme/v1.3/markers/n/mark_b.png",
            position: [modal.store.lng, modal.store.lat]
        });
        marker.setMap(map);

        AMap.event.addListener(marker, 'click', function() {
            infoWindow.open(map, marker.getPosition());
        });

        var info='<div class="info"><div class="info-top" style="cursor: pointer"><img src="https://webapi.amap.com/images/close2.gif"></div><div class="info-middle" style="background-color: white;"><div class="info-title"> '+modal.store.storename+'</div><div class="info-window"><div class="address">' + modal.store.address + '</div><div class="navi" ><a class="tag">到这里去</a><div class="js-navi-to navi-to" style="cursor: pointer"></div></div></div></div><div class="info-bottom" style="position: relative; top: 0px; margin: 0px auto;"><img src="https://webapi.amap.com/images/sharp.png"></div></div>';
        var infoWindow= new AMap.InfoWindow({
            isCustom: true,  //使用自定义窗体-易----福--网
            content:  info,
            offset: new AMap.Pixel(16, -50)//-113, -140
        });

        AMap.event.addListener(infoWindow, 'open', function() {

            $(document).on('click', '.info-top',function () {
                map.clearInfoWindow();
            });

            $(document).on('click', '.js-navi-to',function () {
                window.location.href = 'http://uri.amap.com/navigation?to='+modal.store.lng+','+modal.store.lat+','+modal.store.storename+'&midwaypoint&mode=car&policy=1&src=mypage&coordinate=gaode&callnative=0';
            });
        });

        infoWindow.open(map, map.getCenter());

        $('.fui-footer').css('visibility', 'visible');
        FoxUI.loader.hide();
          }, 250);
    };
    return modal;
});