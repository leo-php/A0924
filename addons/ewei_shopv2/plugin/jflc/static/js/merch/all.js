define(['core', 'tpl'], function (core, tpl) {
    var modal = {
        page: 1
    };
    modal.init = function (params) {
        $('.fui-content').infinite({
            onLoading: function () {
                modal.getList()
            }
        });
        if (modal.page == 1) {
            modal.getList()
        }
    };

    modal.getList = function () {
        core.json('jflc/merch/get_all', {
            page: modal.page
        }, function (ret) {
            var result = ret.result;
            if (result.total <= 0) {
                $('.container').hide();
                $('.content-empty').show();
                $('.fui-content').infinite('stop')
            } else {
                $('.container').show();
                $('.content-empty').hide();
                $('.fui-content').infinite('init');
                if (result.list.length <= 0 || result.list.length < result.pagesize) {
                    $('.fui-content').infinite('stop')
                }
            }
            modal.page++;
            core.tpl('.container', 'tpl_commission_down_list', result, modal.page > 1)
        })
    };
    return modal
});