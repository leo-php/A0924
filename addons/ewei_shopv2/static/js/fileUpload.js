/**
 * 文件创建对象
 * @param containerId 文件容器ID
 */
let WpFileUpload = function (containerId) {
    // 验证参数是否有
    if(WpParamTools.isNullOrEmpty(containerId)){
        // 容器Id不存在
        WpFileUploadMessage.error(WpFileUploadMessageModel.notExitUploadId);
        return;
    }
    // 文件操作对象，添加默认配置
    let wfu = {
        "uploadId":containerId,
        // 必须，上传地址
        "uploadUrl":"#",
        // 可选，获取进去信息的url
        "progressUrl":"#",
        // 模拟进度上传
        "scheduleStandard":false,
        // 自定义文件上传按钮id
        "selfUploadBtId":"",
        // 记住上传文件文件
        "rememberUpload":false,
        // 是否自动上传
        "autoCommit":false,
        // 是否隐藏上传按钮
        "isHiddenUploadBt":false,
        // 是否隐藏清除按钮
        "isHiddenCleanBt":false,
        // 是否上传完成后自动清除
        "isAutoClean":false,
        // 是否可以拖动
        "canDrag":true,
        // 文件类型
        "fileType":"*",
        // 文件大小限制,单位KB
        "size":"-1",
        // 文件总大小限制，单位KB
        "totalSize":"-1",
        // 文件数量限制
        "maxFileNumber":"-1",
        // 是否选择多文件
        "ismultiple":true,
        // 显示总进度条
        "showSummerProgress":true,
        // 是否显示文件单个总进度
        "showFileItemProgress":true,
        // 是否显示进度值
        "showProgressNum":false,
        // 要上传的文件参数
        "uploadFileParam":"files",
        // 要上传的文件参数是否迭代，如果true：files0，files1，files2......
        // 如果是false：所有的文件通过一个参数files来上传文件
        "uploadFileParamIteration":false,
        // 上传成功后返回的数据存储在此
        "resultData":null,

        "beforeUpload":function(){// 在上传前面执行的回调函数
        },
        "onUpload":function(){// 在上传之后
        }
    };
    // 设置初始化函数
    wfu.initUpload = WpFileUploadEvent.initUpload;
    // 设置回显文件事件
    wfu.showFileResult = WpFileUploadEvent.showFileResult;
    // 删除回显的单个文件
    wfu.removeShowFileItem = WpFileUploadEvent.removeShowFileItem;
    // 获取表单数据
    wfu.getFormData = WpFileUploadFormTools.getFormData;
    // 获取上传文件格式的表单数据
    wfu.getFormDataOfUploadFile = WpFileUploadFormTools.getFormDataOfUploadFile;
    // 上传失败调用
    wfu.uploadError = WpFileUploadTools.uploadError;
    // 上传成功调用
    wfu.uploadSuccess = WpFileUploadTools.uploadSuccess;
    // 上传文件
    wfu.upload = WpFileUploadTools.uploadFileOfWfuEvent;
    return wfu;
};
/**
 * 文件上传参数工具
 */
let WpParamTools = {
    /**
     * 是否为空或者为null
     * @param param 要验证的参数
     *  @return {boolean} 返回：true:参数为空或者null，false:参数不为空或者null
     */
    "isNullOrEmpty" : function (param) {
        return  null == param || $.trim(param) === "";
    },
    /**
     * 数组参数是否为空或者为null
     * @param arrayObj 要验证的数组对象
     * @return {boolean} 返回true:为空或者为null,false:存在且数组不为空
     */
    "isNullOrEmptyOfArray" : function(arrayObj){
        return null == arrayObj || arrayObj.length <= 0;
    },
    /**
     * 验证参数是否只是一个对象，数组除外
     * @param param 要验证的参数
     * @returns {boolean} 返回true:是个对象，false:是个字符串，数组或者其它类型数据
     */
    "isOnlyObject" : function(param){
        return param.constructor === Object;
    }

};
/**
 * 文件上传事件调用工具
 */
let WpFileUploadEvent = {
    /**
     * 初始化上传:
     *    初始化界面
     *    初始化按钮事件
     *
     * @param opt 要设置的参数，更改默认参数设置
     */
    "initUpload" : function(opt){
        // 获取对象，作为全局变量，方便修改
        let wfu = this;
        // 设置自定义配置
        if(null != opt){
            if (!WpParamTools.isOnlyObject(opt)) {
                // 初始化配置格式错误
                WpFileUploadMessage.error(WpFileUploadMessageModel.initConfigFormatError);
                return;
            }
            // 遍历默认参数，设置自定义参数配置
            $.each(wfu, function (key) {
                if (!WpParamTools.isNullOrEmpty(opt[key])) {
                    wfu[key] = opt[key];
                }
            });
        }
        // 初始化布局
        WpFileUploadEvent.initWithLayout(wfu);
        // 初始化拖拽
        WpFileUploadEvent.initWithDrag(wfu);
        // 初始化选择文件
        WpFileUploadEvent.initWithSelectFile(wfu);
        // 初始化清除文件
        WpFileUploadEvent.initWithCleanFile(wfu);
        // 初始化文件上传
        WpFileUploadEvent.initWithUpload(wfu);
        // 初始化对象文件
        WpFileUploadFileList.initFileList(wfu);
    },
    /**
     * 初始化布局
     * @param wfu 要初始化的对象
     */
    "initWithLayout" : function(wfu){
        let uploadId = wfu.uploadId;
        let fileContanObj =  $("#"+uploadId);
        // 添加上传头部按钮集合
        fileContanObj.append(WpFileUploadViewsModel.getHeadButtonsView(wfu));
        // 添加总进度条
        fileContanObj.append(WpFileUploadViewsModel.getSummerProgress(wfu));
        // 添加文件上传的显示容器
        fileContanObj.append(WpFileUploadViewsModel.getFileContainBox());
    },
    /**
     * 初始化拖拽
     * @param wfu 初始化操作的对象
     */
    "initWithDrag" : function(wfu){
        let canDrag = wfu.canDrag;
        let uploadId = wfu.uploadId;
        // 文件上传容器
        let containObj = $("#"+uploadId);
        // 文件存放的容器
        let containBoxObj = containObj.find(".box").get(0);
        if(canDrag){
            $(document).on({
                dragleave:function(e){// 拖离 
                    e.preventDefault();
                },
                drop:function(e){// 拖后放 
                    e.preventDefault();
                },
                dragenter:function(e){// 拖进 
                    e.preventDefault();
                },
                dragover:function(e){// 拖来拖去 
                    e.preventDefault();
                }
            });
            if(containBoxObj != null){
                // 验证图片格式，大小，是否存在
                containBoxObj.addEventListener("drop",function(e) {
                    if(containObj.attr("isUpload") === "true"){
                        e.preventDefault();
                    }else{
                        WpFileUploadEvent.dragListingEvent(e,wfu);
                    }
                });
            }
        }
    },
    /**
     * 初始化选择文件按钮
     * @param wfu 初始化操作的对象
     */
    "initWithSelectFile" : function(wfu){
        let uploadId = wfu.uploadId;
        let selectFileBtObj = $("#"+uploadId+" .uploadBts .selectFileBt");
        selectFileBtObj.css("background-color","#0099FF");
        selectFileBtObj.off();
        selectFileBtObj.on("click",function(){
            if(wfu.autoCommit){
                WpFileUploadEvent.cleanFileEvent(wfu);
            }
            WpFileUploadEvent.selectFileEvent(wfu);
        });
    },
    /**
     * 初始化清除文件
     * @param wfu 初始化操作的对象
     */
    "initWithCleanFile" : function (wfu) {
        let uploadId = wfu.uploadId;
        if(!wfu.isHiddenCleanBt){
            let cleanBtObj = $("#"+uploadId+" .uploadBts .cleanFileBt");
            let cleanBtObjIcon = $("#"+uploadId+" .uploadBts .cleanFileBt i");
            cleanBtObj.off();
            cleanBtObj.on("click",function(){
                WpFileUploadEvent.cleanFileEvent(wfu);
            });
            cleanBtObjIcon.css("color","#0099FF");
        }
    },
    /**
     * 选择文件事件
     * @param wfu 初始化操作的对象
     */
    "selectFileEvent" : function(wfu){
        let uploadId = wfu.uploadId;
        let ismultiple = wfu.ismultiple;
        let inputObj=document.createElement('input');
        inputObj.setAttribute('id',uploadId+'_file');
        inputObj.setAttribute('type','file');
        inputObj.setAttribute("style",'visibility:hidden');
        if(ismultiple){// 是否选择多文件
            inputObj.setAttribute("multiple","multiple");
        }
        $(inputObj).on("change", function(){
            WpFileUploadEvent.selectFileChangeEvent(this.files, wfu);
        });
        document.body.appendChild(inputObj);
        inputObj.click();
    },
    /**
     * 选择文件，改变文件的事件
     * @param files 选择的文件
     * @param wfu 初始化操作的对象
     */
    "selectFileChangeEvent" : function (files, wfu) {
        // 添加文件到列表
        WpFileUploadTools.addFileList(files, wfu);
        // 清除input选择文件
        WpFileUploadTools.cleanFilInputWithSelectFile(wfu);
        // 如果是开启自动提交，则启动提交
        if(wfu.autoCommit){
            WpFileUploadTools.uploadFileEvent(wfu);
        }
    },
    /**
     * 清除文件事件
     * @param wfu 操作的对象
     */
    "cleanFileEvent" : function(wfu){
        let uploadId = wfu.uploadId;
        if(wfu.showSummerProgress){
            // 设置进度条关闭
            WpFileUploadTools.setProgressShow(uploadId, false);
            // 设置进度条长度
            WpFileUploadTools.setProgressNumber(wfu, 0);
        }
        // 清楚input文件选择
        WpFileUploadTools.cleanFilInputWithSelectFile(wfu);
        // 设置文件为空
        WpFileUploadFileList.setFileList([],wfu);
        // 删除要上传的文件，但是注意，不会上传已经上传了的文件
        WpFileItemTools.getNeedUploadItemArray(uploadId).remove();
        WpFileUploadTools.changeUploadButtonsStatus(wfu,2);
    },
    /**
     * 拖动监听事件
     * @param wfu 操作的对象
     */
    "dragListingEvent" : function(wfu){

    },
    /**
     * 初始化文件上传
     * @param wfu 操作的对象
     */
    "initWithUpload" : function(wfu){
        let uploadId = wfu.uploadId;
        if(!wfu.isHiddenUploadBt){
            // 上传文件按钮
            let uploadFileBt = $("#"+uploadId+" .uploadBts .uploadFileBt");
            uploadFileBt.off();
            uploadFileBt.on("click",function(){
                WpFileUploadTools.uploadFileEvent(wfu);
            });
            // 上传按钮图标
            let uploadFileBtIcon = $("#"+uploadId+" .uploadBts .uploadFileBt i");
            uploadFileBtIcon.css("color","#0099FF");
        }
        if(wfu.selfUploadBtId != null && wfu.selfUploadBtId !== ""){
            if(WpFileUploadTools.foundExitById(wfu.selfUploadBtId)){
                // 自定义上传按钮
                let selfUploadBt = $("#"+wfu.selfUploadBtId);
                selfUploadBt.off();
                selfUploadBt.on("click",function(){
                    WpFileUploadTools.uploadFileEvent(wfu);
                });
            }
        }
    },
    /**
     * 增加开始上传文件标识
     * @param wfu 要操作的对象
     */
    "startUpload":function(wfu){
        let uploadId = wfu.uploadId;
        $("#"+uploadId).attr("isUpload","true")
    },
    /**
     * 去除开始上传文件标识
     * @param wfu 要操作的对象
     */
    "stopUpload":function(wfu){
        let uploadId = wfu.uploadId;
        $("#"+uploadId).removeAttr("isUpload");
    },
    /**
     * 删除文件
     * @param wfu 操作的对象
     */
    "initWithDeleteFile" : function (wfu) {
        let uploadId = wfu.uploadId;
        let fileItemViewArray = WpFileItemTools.getNeedUploadItemArray(uploadId)
        let fileItemDeleteBt = WpFileItemTools.getFileViewStatus(fileItemViewArray);
        fileItemDeleteBt.off();
        fileItemDeleteBt.on("click",function(){
            WpFileUploadEvent.deleteFileEvent(wfu, this);
        })
    },
    /**
     * 删除文件时间
     * @param wfu 操作的对象
     * @param obj 操作的文件对象
     */
    "deleteFileEvent" : function (wfu, obj){
        let fileItem = $(obj).parent().parent();
        let fileCodeId = fileItem.attr("fileCodeId");
        let fileListArray = WpFileUploadFileList.getFileList(wfu);
        delete fileListArray[fileCodeId];
        WpFileUploadFileList.setFileList(fileListArray,wfu);
        fileItem.remove();
    },
    /**
     * 回显文件
     * @param fileUrl 文件地址，必须，文件的路径地址
     * @param fileId 文件操作的id，
     * @param deleteFile 是否删除文件
     * @param defineFileName 是否下载文件
     * @param deleteEvent 删除文件执行的事件
     * @param defineFileName 自定义文件名，如果不填写或者为null将从链接中获取文件名
     * @param downLoadEvent 下载触发事件
     */
    "showFileResult" : function(fileUrl,fileId,defineFileName,deleteFile,downloadFile,deleteEvent,downLoadEvent){
        // 获取对象，作为全局变量，方便修改
        let wfu = this;
        // 上传的Id
        let uploadId = wfu.uploadId;
        if (null == fileUrl || fileUrl == "" || uploadId == null || uploadId == "" ) {
            return;
        }
        let boxJsObj =  $("#"+uploadId+" .box").get(0);

        // 设置文件名
        let fileName = defineFileName;
        // 根据文件地址获取文件名称
        if(WpParamTools.isNullOrEmpty(defineFileName)){
            fileName = WpFileUploadTools.getFileNameWithUrl(fileUrl);
        }
        // 文件类型
        let fileType = WpFileUploadTools.getSuffixNameByFileName(fileName)
        // 是否是图片
        let isImg = WpFileUploadTools.isInArray(fileType, WpFileUploadTools.imgArray);
        // 文件类型大写
        fileType = fileType.toUpperCase();
        // 获取回显文件模版
        let modelStr = WpFileUploadViewsModel.getFileItemResultModel(fileType, fileId, fileName, isImg, fileUrl, deleteFile, downloadFile);
        $(boxJsObj).append(modelStr);
        // 如果删除文件，则添加删除文件的事件
        if(deleteFile) {
            let fileItem = WpFileItemTools.getIsUploadItem(uploadId, fileId);
            WpFileItemTools.getFileViewRemove(fileItem).mousedown(function () {
                if(deleteEvent != null && deleteEvent != "" && (typeof deleteEvent === "function") ) {
                    deleteEvent(fileId);
                }
            });
        }
        // 执行下载函数
        if(downloadFile) {
            let fileItem = WpFileItemTools.getIsUploadItem(uploadId, fileId);
            WpFileItemTools.getFileViewDown(fileItem).mousedown(function () {
                if(deleteEvent != null && deleteEvent != "" && (typeof deleteEvent === "function") ) {
                    downLoadEvent(fileId,fileUrl);
                }
            });
        }
    },
    /**
     * 删除回显成功的单个文件
     * @param fileId 文件ID
     */
    "removeShowFileItem" : function (fileId) {
        // 获取对象，作为全局变量，方便修改
        let wfu = this;
        // 上传的Id
        let uploadId = wfu.uploadId;
        let fileitemObj = WpFileItemTools.getIsUploadItem(uploadId, fileId);
        fileitemObj.remove();
    }

};
/**
 * 文件上传视图模板
 */
let WpFileUploadViewsModel = {
    /**
     * 头部按钮操作视图模板
     * @param wfu 操作的对象
     * @return {string} 头部按钮操作模板
     */
    "getHeadButtonsView" : function(wfu){
        // 选择文件的按钮标题
        let selectFileButtonTitle = '上传图片凭证';
        let btsStr = '';
        btsStr += '<div class="uploadBts">';
        btsStr += '<div>';
        btsStr += '<div class="selectFileBt">'+selectFileButtonTitle+'</div>';
        btsStr += '</div>';
        // 上传按钮
        if(!wfu.isHiddenUploadBt){
            btsStr += '<div class="uploadFileBt">';
            btsStr += '<i class="iconfont icon-shangchuan"></i>';
            btsStr += '</div>';
        }
        // 清理按钮
        if(!wfu.isHiddenCleanBt){
            btsStr += '<div class="cleanFileBt">';
            btsStr += '<i class="iconfont icon-qingchu"></i>';
            btsStr += '</div>';
        }
        btsStr += '</div>';
        return btsStr;
    },
    /**
     * 获取文件总进度条
     * @param wfu 操作对象
     * @return {string} 总进度模板
     */
    "getSummerProgress" : function(wfu){
        let summerProgressStr = '';
        let progressNum = '';
        if(wfu.showProgressNum){
            progressNum = '0%';
        }
        // 添加总进度条
        if(wfu.showSummerProgress){
            summerProgressStr += '<div class="subberProgress">';
            summerProgressStr += '<div class="progress">';
            summerProgressStr += '<div>'+progressNum+'</div>';
            summerProgressStr += '</div>';
            summerProgressStr += '</div>';
        }
        return summerProgressStr;
    },
    /**
     * 获取文件存放的容器
     * @return {string} 存放文件的容器模板
     */
    "getFileContainBox" : function(){
        return '<div class="box"></div>';
    },
    /**
     * 上传显示文件的模板
     * @param isImg 是否式图片：true/false
     * @param fileType 文件类型
     * @param fileName 文件名字
     * @param isImgUrl 如果事文件时的文件地址默认为null
     * @param fileCodeId 文件表使
     */
    "getFileItemModel":function(isImg,fileType,fileName,isImgUrl,fileCodeId){
        // 默认显示类型
        let showTypeStr='<div class="fileType">'+fileType+'</div> <i class="iconfont icon-wenjian"></i>';
        if(isImg){
            // 图片显示类型
            if(isImgUrl != null && isImgUrl !== "null" && isImgUrl !== ""){
                showTypeStr = '<img src="'+isImgUrl+'" alt="'+fileName+'"/>';
            }
        }
        let modelStr="";
        modelStr += '<div class="fileItem" fileCodeId="'+fileCodeId+'">';
        modelStr += '<div class="imgShow">';
        modelStr += showTypeStr;
        modelStr += '</div>';
        modelStr += '<div class="progress">';
        modelStr += '<div class="progress_inner"></div>';
        modelStr += '</div>';
        // modelStr += '<div class="status">';
        // modelStr += '<i class="iconfont icon-shanchu"></i>';
        // modelStr += '</div>';
        // modelStr += '<div class="fileName">';
        // modelStr += fileName;
        // modelStr += '</div>';
        modelStr += '</div>';
        return modelStr;
    },
    /**
     * 回显文件模版
     * @param fileType 文件类型
     * @param fileId 文件ID
     * @param fileName 文件名字
     * @param isImg 是否是图片
     * @param fileUrl 文件URL
     * @param deleteFile 是否删除文件，true：删除文件，false：不能删除文件
     * @returns {string} 返回回显文件的模版
     */
    "getFileItemResultModel" : function (fileType, fileId, fileName, isImg, fileUrl, deleteFile, downloadFile) {
        //默认显示类型
        let showTypeStr = '<div class="fileType">'+fileType+'</div> <i class="iconfont icon-wenjian"></i>';
        //是否是文件
        if (isImg) {
            //图片显示类型
            if (fileUrl != null && fileUrl != "null" && fileUrl != "") {
                showTypeStr = '<img src="'+fileUrl+'"/>';
            }
        }
        let showImgStyle = 'imgShow';
        if (!deleteFile) {
            showImgStyle+=" imgShowResult";
        }
        let modelStr = '';
        modelStr += '<div class="fileItem" showFileCode="'+fileId+'">';
        modelStr += '<div class="'+showImgStyle+'">';
        modelStr += showTypeStr;
        modelStr += '</div>';
        if (downloadFile) {
            modelStr += '<div class="down">';
            modelStr += '<i class="iconfont icon-xiazai"></i>';
            modelStr += '</div>';
        }
        if (deleteFile) {
            modelStr += '<div class="status">';
            modelStr += '<i class="iconfont icon-shanchu"></i>';
            modelStr += '</div>';
        }
        modelStr += '<div class="fileName">';
        modelStr += fileName;
        modelStr += '</div>';
        modelStr += '</div>';
        return modelStr;
    }
};
/**
 * 文件上传Ajax操作
 */
let WpFileUploadAjax = {
    /**
     * 开始上传文件
     * @param wfu 调用的对象
     * @param formData 封装的参数
     * @param rememberFile 记住已上传文件的对象
     */
    "startUploadFile" : function (wfu,formData,rememberFile) {
        $.ajax({
            type:"post",
            url:wfu.uploadUrl,
            data:formData,
            processData : false,
            contentType : false,
            success:function(data){
                // 更新记住我的缓存
                WpFileUploadFileList.flushRememberFile(rememberFile,wfu);
                // 记录上传成功后的结果
                wfu.resultData = data;
                // 调用上传结束后的事件
                setTimeout(function () { wfu.onUpload() },500);
                // 自定清除文件
                if (wfu.isAutoClean){
                    setTimeout(function () {WpFileUploadEvent.cleanFileEvent(wfu);},2000) ;
                }
            },
            error:function(e){
                // 显示上传错误
                wfu.uploadError();
            }
        });
    }
};
/**
 * 文件列表操作
 * @type {{}}
 */
let WpFileUploadFileList = {
    /**
     * 初始化文件
     * @param wfu 要操作的对象
     */
    "initFileList":function(wfu){
        wfu.fileList = new Array();
    },
    /**
     * 获取文件列表
     * @param wfu 要操作的对象
     * @return {Array} 返回文件列表
     */
    "getFileList":function(wfu){
        // 如果未进行初始化则进行初始化
        if (null == wfu.fileList){
            WpFileUploadFileList.initFileList(wfu);
        }
        return wfu.fileList;
    },
    /**
     * 设置文件列表
     * @param fileList 文件列表
     * @param wfu
     */
    "setFileList":function(fileList,wfu){
        wfu.fileList = fileList;
    },
    /**
     * 更新记住文件
     * @param fileList 要更新的文件列表
     * @param wfu 要操作的对象
     */
    "flushRememberFile":function(fileList,wfu){
        if(wfu.rememberUpload){
            // 记住文件是否为空
            let rememberFileIsEmpty = wfu.rememberFile == null || wfu.rememberFile === "" || wfu.rememberFile.length === 0;
            if(rememberFileIsEmpty){
                wfu.rememberFile = wfu.fileList;
            }else{
                let rememberFileArray = wfu.rememberFile;
                for(let i=0;i<fileList.length;i++){
                    rememberFileArray[rememberFileArray.length] = fileList[i];
                }
                wfu.rememberFile = rememberFileArray;
            }
        }
    },
    /**
     * 获取文件上传总数据量
     * @param wfu 操作的对象
     * @returns {number} 返回文件上传总大小
     */
    "getFilesDataAmount":function(wfu){
        let fileList = WpFileUploadFileList.getFileList(wfu);
        let summer = 0;
        for (let i = 0; i < fileList.length; i++) {
            let fileItem = fileList[i];
            if (fileItem != null){
                summer = parseFloat(summer)+fileItem.size;
            }
        }
        return summer;
    },
    /**
     * 获取文件个数
     * @param wfu 操作的对象
     */
    "getFileNumber":function(wfu){
        let number = 0;
        let fileList = WpFileUploadFileList.getFileList(wfu);
        for(let i = 0; i < fileList.length; i++){
            if(fileList[i] != null){
                number++;
            }
        }
        return number;
    },
    /**
     * 文件是否已经存在
     * @param file 要验证的文件
     * @param wfu 操作的对象
     * @returns {boolean} true:文件已经在上传的列表中了，false：文件不存在上传列表中
     * */
    "fileIsExit":function(file,wfu){
        let fileList = WpFileUploadFileList.getFileList(wfu);
        let ishave = false;
        for(let i = 0; i < fileList.length; i++){
            let fileItem = fileList[i];
            // 文件名相同，文件大小相同
            if(null != fileItem && fileItem.name === file.name && fileItem.size === file.size){
                ishave = true;
            }
        }
        return ishave;
    },
    /**
     * 文件是否已经被上传
     * @param file 要验证的文件
     * @param wfu 操作的对象
     * @returns {boolean} true:文件已经上传过了，false：文件没有上传过
     */
    "fileIsHaveUpload":function(file,wfu){
        let fileList = wfu.rememberFile;
        let ishave = false;
        if(fileList!=null){
            for(let i = 0; i < fileList.length; i++){
                let fileItem = fileList[i];
                // 文件名相同，文件大小相同
                if(null != fileItem && fileItem.name === file.name && fileItem.size === file.size){
                    ishave = true;
                }
            }
        }
        return ishave;
    },
    /**
     * 获取文件总大小
     * @param wfu 操作的对象
     */
    "getFileTotalSize" : function (wfu) {
        let fileList = WpFileUploadFileList.getFileList(wfu);
        let totalSize = 0;
        for(let i = 0; i < fileList.length; i++){
            let fileItem = fileList[i];
            if (null != fileItem) {
                totalSize += parseInt(fileItem.size);
            }
        }
        return totalSize;
    },
};
/**
 * 文件上传其它工具
 */
let WpFileUploadTools = {
    "imgArray" : ['jpg','png','jpeg','bmp','gif','webp'],
    /**
     * 设置进度条的显示
     * @param uploadId 文件上传容器Id
     * @param isShow 是否显示
     */
    "setProgressShow":function(uploadId, isShow){
        let subberProgressParent = $("#"+uploadId+" .subberProgress");
        let display = isShow ? "block" : "none";
        subberProgressParent.css("display", display);
    },
    /**
     * 设置进度条的数值
     * @param wfu 操作的对象
     * @param percentNum 进度百分比，如：60% 这里填写的值为60
     */
    "setProgressNumber":function(wfu, percentNum){
        let uploadId = wfu.uploadId;
        let subberProgress = $("#"+uploadId+" .subberProgress .progress>div");
        // 格式化数据
        percentNum = percentNum+"%";
        // 设置长度
        subberProgress.css("width",percentNum);
        // 是否显示值
        if(wfu.showProgressNum){
            subberProgress.html(percentNum);
        }
    },
    /**
     * 添加文件到box文件列表
     * @param fileList 选择的文件列表
     * @param wfu 要操作的对象
     */
    "addFileList" : function (fileList, wfu) {
        let uploadId = wfu.uploadId;
        let boxJsObj =  $("#"+uploadId+" .box").get(0);
        // 获取文件队列
        let fileListArray=WpFileUploadFileList.getFileList(wfu);
        // 获取文件数量，不能直接fileListArray获取length,因为里面包含了null
        let fileNumber = WpFileUploadFileList.getFileNumber(wfu);
        // 是否超出文件数量限制
        let isOutOfFileNumber = wfu.maxFileNumber !== "-1" && ((parseInt(fileNumber) + fileList.length) > parseInt(wfu.maxFileNumber));
        if(isOutOfFileNumber){
            WpFileUploadMessage.error(WpFileUploadMessageModel.outMaxFileNumber(wfu.maxFileNumber));
            return;
        }
        // 图片文件测试
        let imgtest=/image\/(\w)*/;
        // 文件类型集合
        let fileTypeArray = wfu.fileType;
        // 文件大小限制
        let fileSizeLimit = wfu.size;
        // 文件总大小限制
        let fileTotalSizeLimit = wfu.totalSize;
        for(let i = 0; i < fileList.length; i++){
            let fileItem = fileList[i];

            // 判断文件是否存在
            if(WpFileUploadFileList.fileIsExit(fileItem, wfu)){
                // 文件已经存在
                WpFileUploadMessage.error(WpFileUploadMessageModel.fileIsExit(fileItem.name));
                continue;
            }
            // 是否记住上传文件
            if(wfu.rememberUpload){
                if(WpFileUploadFileList.fileIsHaveUpload(fileItem,wfu)){
                    WpFileUploadMessage.error(WpFileUploadMessageModel.fileIsHaveUpload(fileItem.name));
                    continue;
                }
            }
            // 文件总大小判断
            let isOutOfTotalSize = fileTotalSizeLimit !== '-1' && (WpFileUploadFileList.getFileTotalSize(wfu) + fileItem.size > (fileTotalSizeLimit*1000));
            if (isOutOfTotalSize){
                WpFileUploadMessage.error(WpFileUploadMessageModel.outOfTotalSize(fileTotalSizeLimit));
                continue;
            }
            // 单个文件大小显示判断
            let isOutOfSize = fileSizeLimit !== '-1' && fileItem.size > (fileSizeLimit*1000);
            if(isOutOfSize){
                WpFileUploadMessage.error(WpFileUploadMessageModel.outOfSize(fileItem.name,fileSizeLimit));
                continue;
            }
            // 获取文件后缀
            let fileTypeStr =  WpFileUploadTools.getSuffixNameByFileName(fileItem.name);
            // 文件是否在限定类型内
            let fileIsInType = fileTypeArray === "*" || WpFileUploadTools.isInArray(fileTypeStr, fileTypeArray);
            if (fileIsInType) {
                // 文件名大写
                let fileTypeUpcaseStr = fileTypeStr.toUpperCase();
                let fileModel = "";
                if (imgtest.test(fileItem.type)) {
                    // 获取图片文件路径
                    let imgUrlStr = WpFileUploadTools.getImgUrlOfLocal(fileItem);
                    fileModel = WpFileUploadViewsModel.getFileItemModel(true,fileTypeUpcaseStr,fileItem.name,imgUrlStr,fileListArray.length);
                }else{
                    fileModel = WpFileUploadViewsModel.getFileItemModel(false,fileTypeUpcaseStr,fileItem.name,null,fileListArray.length);
                }
                $(boxJsObj).append(fileModel);
                // 初始化单文件操作
                WpFileUploadEvent.initWithDeleteFile(wfu);

                fileListArray[fileListArray.length] = fileList[i];
            }else{
                WpFileUploadMessage.error(WpFileUploadMessageModel.notSupperFileType(fileItem.name));
            }
        }
        // 设置文件
        WpFileUploadFileList.setFileList(fileListArray,wfu);
    },
    /**
     * 清楚input选择文件
     * @param wfu 操作的对象
     */
    "cleanFilInputWithSelectFile" : function(wfu){
        let uploadId = wfu.uploadId;
        $("#"+uploadId+"_file").remove();
    },
    /**
     * 上传文件
     * @param wfu 操作的对象
     */
    "uploadFileEvent" : function(wfu){
        if(null != wfu.beforeUpload && (typeof wfu.beforeUpload === "function")) {
            wfu.beforeUpload();
        }
        WpFileUploadTools.uploadFile(wfu);
    },
    /**
     * wfu调用的上传事件
     */
    "uploadFileOfWfuEvent" : function () {
        let wfu = this;
        WpFileUploadTools.uploadFileEvent(wfu);
    },
    /**
     * 查找某个对象是否存在
     * @param id 要查找的Id
     * @returns {boolean} true:存在，false:不存在
     */
    "foundExitById":function(id){
        return $("#"+id).size() > 0;
    },

    /**
     * 获取文件名后缀
     * @param fileName 文件名全名
     * */
    "getSuffixNameByFileName":function(fileName){
        let str = fileName;
        let index = str.lastIndexOf(".");
        if (index < 0) {
            return "未知";
        }
        let pos = index+1;
        return str.substring(pos,str.length);
    },
    /**
     * 判断某个值是否在这个数组内
     * */
    "isInArray":function(strFound,arrays){
        let ishave = false;
        for (let i = 0; i < arrays.length; i++) {
            if(strFound === arrays[i] || strFound.toLowerCase() === arrays[i]){
                ishave = true;
                break;
            }
        }
        return ishave;
    },
    /**
     * 获取图片文件的本地路径
     * @param fileItem 文件对象
     */
    "getImgUrlOfLocal":function(fileItem){
        // 获取文件路径
        let imgUrlStr = "";
        if (window.createObjectURL !== undefined) { //  basic
            imgUrlStr = window.createObjectURL(fileItem);
        } else if (window.URL !== undefined) { // mozilla(firefox)
            imgUrlStr = window.URL.createObjectURL(fileItem);
        } else if (window.webkitURL !== undefined) { // webkit or chrome
            imgUrlStr = window.webkitURL.createObjectURL(fileItem);
        }
        return imgUrlStr;
    },
    /**
     * 添加上传文件到formData,并且记录上传文件
     * @param wfu 操作的对象
     * @param formData 要被封装的对象
     * @param rememberFile 记住文件要设置的数组
     */
    "addUploadFileToFormData":function(wfu, formData, rememberFile){
        // 获取所有要上传的文件列表
        let fileList = WpFileUploadFileList.getFileList(wfu);
        // 参数迭代数字,file0,file1,file2.....
        let paramNum = 0;
        for (let i = 0; i < fileList.length; i++) {
            if (fileList[i] == null) {
                continue;
            }
            let fileItem = fileList[i];
            // 如果参数不是迭代
            if (!wfu.uploadFileParamIteration) {
                formData.append(wfu.uploadFileParam, fileItem);
            } else {
                formData.append(wfu.uploadFileParam + paramNum++, fileItem);
            }
            // 记住文件
            WpFileUploadTools.addUploadFileRemember(wfu, rememberFile, fileItem);
        }
    },
    /**
     * 添加上传文件要记住的文件
     * @param wfu 操作对象
     * @param rememberFiles 要操作记住文件的对象
     * @param fileItem 要被记录的文件
     */
    "addUploadFileRemember" : function (wfu, rememberFiles, fileItem) {
        if (wfu.rememberFile && null != rememberFiles && null != fileItem){
            rememberFiles[rememberFiles.length] = fileItem;
        }
    },

    /**
     * 添加上传文件其它参数
     * @param wfu 要操作的对象
     * @param formData 要被封装的对象
     */
    "addUploadParamToFormData" : function (wfu, formData) {
        if (null != wfu.otherData && wfu.otherData != "" ) {
            for (let j = 0; j < wfu.otherData.length; j++) {
                formData.append(wfu.otherData[j].name, wfu.otherData[j].value);
            }
        }
    },
    /**
     * 上传文件
     * @param wfu 要操作的对象
     */
    "uploadFile" : function (wfu) {
        WpFileUploadEvent.startUpload(wfu);
        let uploadUrl = wfu.uploadUrl;

        // 记住我文件数组
        let rememberFile = [];
        // 数据封装对象
        let formData = new FormData();
        let fileNumber = WpFileUploadFileList.getFileNumber(wfu);
        if (fileNumber <= 0){
            // 没有要上传的文件
            WpFileUploadMessage.info(WpFileUploadMessageModel.noFileUpload);
            return;
        }
        // 添加文件到formData
        WpFileUploadTools.addUploadFileToFormData(wfu,formData,rememberFile);
        // 添加上传的其他参数到formData
        WpFileUploadTools.addUploadParamToFormData(wfu,formData);

        if (uploadUrl !== "#" && uploadUrl != "") {
            // 禁用影响上传的按钮
            WpFileUploadTools.changeUploadButtonsStatus(wfu, 0);
            // 开始上传文件
            WpFileUploadAjax.startUploadFile(wfu,formData,rememberFile);

        }else if (wfu.scheduleStandard) {
            // 提示警告信息,并且开始模拟上传
            WpFileUploadMessage.warn(WpFileUploadMessageModel.noUploadUrl);
            // 禁用影响上传的按钮
            WpFileUploadTools.changeUploadButtonsStatus(wfu, 0);
            // 模拟上传成功
            WpFileUploadFileList.flushRememberFile(rememberFile, wfu);

        }
        // 获取进度
        WpFileUploadTools.getFileUploadProgressMsg(wfu);
    },
    /**
     * 获取上传进度
     * @param wfu
     */
    "getFileUploadProgressMsg" : function (wfu) {
        let uploadId = wfu.uploadId;
        let progressUrl = wfu.progressUrl;

        if(wfu.showSummerProgress){
            $("#"+uploadId+" .subberProgress").css("display","block");
        } else if (!wfu.showFileItemProgress) {
            //如果主进度条不显示，单文件进度不显示，则不进行模拟进度，模拟进度设置将无效
            return;
        }
        let fileItemView = WpFileItemTools.getNeedUploadItemArray(uploadId);
        WpFileItemTools.getFileViewStatus(fileItemView).removeClass();
        // 开始真实获取进度信息
        if (!wfu.scheduleStandard && progressUrl !== "#" && progressUrl != null && progressUrl !== "") {
            // 获取进度为O的情况多余30次的时候，就显示错误
            let getCount = 20;
            // 设置定时器
            let intervalId = setInterval(function(){
                $.get(progressUrl,{},function(data,status){
                    let percent = data.percent;
                    let bytesRead = data.bytesRead;
                    let items = data.items;
                    if(percent >= 100){
                        clearInterval(intervalId);
                        percent = 100;// 不能大于100
                        WpFileUploadEvent.initWithCleanFile(wfu);
                    }
                    //设置限定次数
                    if (getCount <= 0) {
                        clearInterval(intervalId);
                        wfu.uploadError();
                        return;
                    } else {
                        if (bytesRead == 0) {
                            getCount--;
                        }
                    }
                    WpFileUploadTools.showUploadProgress(wfu, bytesRead, percent,items);
                },"json");
            },500);
        } else {
            // 进行模拟进度上传
            // 获取文件上传总大小
            let filesDataAmount = WpFileUploadFileList.getFilesDataAmount(wfu);
            // 所占百分比
            let percent = 0;
            // 已度数据大小
            let bytesRead = 0;

            // 如果进行模拟进度上传
            if (wfu.scheduleStandard) {
                // 创建一个模拟上传速度,5秒能传完的速度,500微妙执行一次，也就是计算10次
                let speedSchedule = WpFileUploadComputer.div(filesDataAmount , 10);
                // 创建定时器，进行模拟进度演示
                let intervalId = setInterval(function(){
                    bytesRead = WpFileUploadComputer.add(bytesRead,speedSchedule);
                    percent = WpFileUploadComputer.div(bytesRead , filesDataAmount) * 100;
                    //取两位小数
                    percent = percent.toFixed(2);
                    if(percent >= 100){
                        clearInterval(intervalId);
                        percent = 100;// 不能大于 100
                        WpFileUploadEvent.initWithCleanFile(wfu);
                    }
                    // 更新进度条
                    WpFileUploadTools.showUploadProgress(wfu, bytesRead, percent);
                },500);
            } else {
                //一次进入60%，然后停止，直到完成
                bytesRead = 0.6 * filesDataAmount;
                percent = 60;
                WpFileUploadEvent.initWithCleanFile(wfu);
                // 更新进度条
                WpFileUploadTools.showUploadProgress(wfu, bytesRead, percent);
            }
        }
    },
    /**
     * 显示进度条
     * @param wfu 要操作的对象
     * @param bytesRead 已经读取的数量
     * @param percent 已经读取的百分比
     * @param items 当前是第几个文件
     */
    "showUploadProgress":function(wfu,bytesRead,percent,items){
        let fileListArray = WpFileUploadFileList.getFileList(wfu);
        // 如果显示主进度条
        if (wfu.showSummerProgress) {
            // 设置主进度条比例
            WpFileUploadTools.setProgressNumber(wfu, percent);
            if (percent == 100) {
                //自定清理
                if (wfu.isAutoClean) {
                    setTimeout(function () {
                        WpFileUploadEvent.cleanFileEvent(wfu);
                    },2000) ;
                }
            }
        }
        if (wfu.showFileItemProgress) {
            // 设置每个文件的状态和进度信息
            if (items == null) {
                WpFileUploadTools.showProgramWithNoItem(wfu,bytesRead,percent,fileListArray);
            } else {
                WpFileUploadTools.showProgramWithItem(wfu,bytesRead,percent,fileListArray,items);
            }
        } else {
            let uploadId = wfu.uploadId;
            let fileItemView = WpFileItemTools.getNeedUploadItemArray(uploadId);
            // 文件状态
            let fileItemStatus = WpFileItemTools.getFileViewStatus(fileItemView);
            fileItemStatus.off();
            // 设置单个文件状态
            fileItemStatus.addClass("iconfont icon-gou");
            WpFileUploadTools.changeUploadButtonsStatus(wfu,1);
        }



    },
    /**
     * 显示进度在不知道当前第几个文件的时候
     * @param wfu 操作的对象
     * @param bytesRead 已阅读的数量
     * @param percent 当前进度百分比
     * @param fileListArray 文件列表
     */
    "showProgramWithNoItem" : function(wfu,bytesRead,percent,fileListArray) {
        for (let i = 0; i < fileListArray.length; i++) {
            if (fileListArray[i] == null) {
                continue;
            }
            // 总上传数减去当前文件的大小，剩余的总数
            let testbytesRead = bytesRead - fileListArray[i].size;
            if (testbytesRead < 0) {
                // 如果已经完成任务100%
                if (percent == 100) {
                    //设置失败
                    WpFileUploadTools.setFileItemProgress(wfu, i, 100, 1);
                    bytesRead = bytesRead-fileListArray[i].size;
                } else {
                    //设置比例
                    let fileItemPercent = bytesRead / fileListArray[i].size * 100;
                    //设置成功相应的比例
                    WpFileUploadTools.setFileItemProgress(wfu, i, fileItemPercent, 0);
                    break;
                }
            }else if (testbytesRead >= 0) {
                WpFileUploadTools.setFileItemProgress(wfu, i, 100, 0);
                bytesRead = bytesRead-fileListArray[i].size;
            }
        }
    },
    /**
     * 显示进度知道当前第几个文件的时候
     * @param wfu 操作的对象
     * @param bytesRead 已阅读的数量
     * @param percent 当前进度百分比
     * @param fileListArray 文件列表
     */
    "showProgramWithItem" : function(wfu,bytesRead,percent,fileListArray,items) {
        let itemTemp = 1;
        for (let i = 0; i < fileListArray.length; i++) {
            if ((i+1) > items) {
                break;
            }
            if (fileListArray[i] == null) {
                continue;
            }
            if (percent == 100) {
                WpFileUploadTools.setFileItemProgress(wfu, i, 100, 0);
                continue;
            }

            if (itemTemp < items) {
                itemTemp ++;
                bytesRead = bytesRead - fileListArray[i].size;
                WpFileUploadTools.setFileItemProgress(wfu, i, 100, 0);
            } else if(itemTemp == items) {
                let fileItemPercent = WpFileUploadComputer.mul(WpFileUploadComputer.div(bytesRead , fileListArray[i].size) , 100);
                if (fileItemPercent == 100) {
                    itemTemp ++;
                    bytesRead = bytesRead - fileListArray[i].size;
                }
                WpFileUploadTools.setFileItemProgress(wfu, i, fileItemPercent, 0);
            }


        }
    },
    /**
     * 设置单个文件进度
     * @param wfu 操作的对象
     * @param fileCodeId 文件标记
     * @param percent 百分比
     * @param status 状态，0：成功，1：失败
     */
    "setFileItemProgress" : function (wfu, fileCodeId, percent, status) {
        if (!wfu.showFileItemProgress){
            return;
        }
        let uploadId = wfu.uploadId;

        let fileItemView = WpFileItemTools.getNeedUploadItem(uploadId,fileCodeId);
        // 文件进度
        let fileItemProgress = WpFileItemTools.getFileViewProgress(fileItemView);
        // 文件状态
        let fileItemStatus = WpFileItemTools.getFileViewStatus(fileItemView);
        if (status == 1) {
            if (wfu.showFileItemProgress) {
                fileItemProgress.addClass("error");
                fileItemProgress.css("width", "100%");
            }
            fileItemStatus.addClass("iconfont icon-cha");
        } else if ( status == 0 ) {
            if (wfu.showFileItemProgress) {
                fileItemProgress.css("width", percent + "%");
            }
            if (percent == 100) {
                fileItemStatus.off();
                fileItemStatus.addClass("iconfont icon-gou");
            }
        }

    },
    /**
     * 上传文件失败集体显示
     */
    "uploadError":function(){
        let wfu = this;
        let uploadId = wfu.uploadId;
        // 是否显示单个文件进度条
        if (wfu.showFileItemProgress) {
            let progressObj = WpFileItemTools.getNeedUploadItemArray(uploadId).find(".progress>div");
            progressObj.addClass("error");
            progressObj.css("width","100%");
        } else {
            WpFileUploadTools.changeUploadButtonsStatus(wfu,2);
        }
        let fileItemView = WpFileItemTools.getNeedUploadItemArray(uploadId);
        // 设置单个文件状态
        WpFileItemTools.getFileViewStatus(fileItemView).addClass("iconfont icon-cha");
        // 设置总进度条状态
        WpFileUploadTools.setProgressNumber(wfu,0);
    },
    /**
     * 上传成功
     */
    "uploadSuccess" : function () {
        let wfu = this;
        WpFileUploadTools.setSuccessOfSubmit(wfu);
    },
    /**
     * 改变上传按钮的状态，上传前，之类的
     * @param wfu 操作的对象
     * @param status 操作的状态，0：开始上传 1:成功停止上传 2：失败停止上传
     */
    "changeUploadButtonsStatus":function(wfu, status){
        if (status == 0) {
            // 禁用文件上传
            WpFileUploadTools.disableFileUpload(wfu);
            // 禁用清除文件
            WpFileUploadTools.disableCleanFile(wfu);
            // 禁用文件选择
            WpFileUploadTools.disableFileSelect(wfu);
        } else if (status == 1) {
            // 删除要上传的文件，但是注意，不会上传已经上传了的文件
            if(wfu.isAutoClean) {
                WpFileUploadEvent.cleanFileEvent(wfu);
            } else {
                WpFileUploadEvent.initWithCleanFile(wfu);
                // 初始化上传
                WpFileUploadEvent.initWithUpload(wfu);
                WpFileUploadEvent.initWithSelectFile(wfu);
                WpFileUploadEvent.stopUpload(wfu);
            }

        } else if (status == 2) {
            // 初始化上传
            WpFileUploadEvent.initWithCleanFile(wfu);
            WpFileUploadEvent.initWithUpload(wfu);
            WpFileUploadEvent.initWithSelectFile(wfu);
            WpFileUploadEvent.stopUpload(wfu);
        }
    },
    /**
     * 禁用文件选择
     * @param wfu 操作的对象
     */
    "disableFileSelect":function(wfu){
        let uploadId = wfu.uploadId;
        let selectFileBt = $("#"+uploadId+" .uploadBts .selectFileBt");
        selectFileBt.css("background-color","#DDDDDD");
        selectFileBt.off();
    },
    /**
     * 禁用文件上传
     * @param wfu 操作的对象
     */
    "disableFileUpload":function(wfu){
        if (!wfu.isHiddenUploadBt) {
            let uploadId = wfu.uploadId;
            let uploadFileBt = $("#"+uploadId+" .uploadBts .uploadFileBt");
            let uploadFileBtIcon = $("#"+uploadId+" .uploadBts .uploadFileBt i");
            uploadFileBt.off();
            uploadFileBtIcon.css("color","#DDDDDD");
        }
    },
    /**
     * 禁用文件清除
     * @param wfu 操作的对象
     */
    "disableCleanFile":function(wfu){
        if (!wfu.isHiddenCleanBt) {
            let uploadId = wfu.uploadId;
            let cleanFileBt = $("#"+uploadId+" .uploadBts .cleanFileBt");
            let cleanFileBtIcon = $("#"+uploadId+" .uploadBts .cleanFileBt i");
            cleanFileBt.off();
            cleanFileBtIcon.css("color","#DDDDDD");
        }

    },
    /**
     * 设置上传成功
     * @param wfu 操作的对象
     */
    "setSuccessOfSubmit" : function(wfu) {
        let progressUrl = wfu.progressUrl;
        if(!wfu.scheduleStandard && (progressUrl == null || progressUrl == "" || progressUrl === "#")) {
            let bytesRead = WpFileUploadFileList.getFilesDataAmount(wfu);
            WpFileUploadTools.showUploadProgress(wfu, bytesRead, 100);
        }
    },
    /**
     * 根据图片地址url获取文件的名称
     * @param fileUrl 文件地址Url
     */
    "getFileNameWithUrl" : function(fileUrl) {
        let index = fileUrl.lastIndexOf("/");
        if(index<=0){
            index = fileUrl.lastIndexOf("\\");
        }
        index = index+1;
        let fileName = fileUrl.substring(index,fileUrl.length);
        return fileName;
    }
};
/**
 * 信息提示
 * @type {{}}
 */
let WpFileUploadMessage = {
    /**
     * 错误提示
     * @param message 提示信息
     */
    "error" : function (message) {
        console.error(message)
    },
    /**
     * 普通信息提示
     * @param message 提示信息
     */
    "info" : function(message) {
        console.info(message);
    },
    /**
     * 警告提醒
     * @param message 提示信息
     */
    "warn" : function (message) {
        console.warn(message);
    }
};
/**
 * 信息提示模板
 * @type {{}}
 */
let WpFileUploadMessageModel = {
    /**
     * 容器Id不存在
     */
    "notExitUploadId":"要设定一个容器id!",
    /**
     * 初始化配置格式错误
     */
    "initConfigFormatError":"初始化参数错误:参数类型应该为对象类型，如:{\"uploadUrl\":\"xxxxxx\"}",
    /**
     * 超出最大文件限制
     * @param maxFileNumber 最大文件数量
     * @return {string} 返回信息内容
     */
    "outMaxFileNumber" : function (maxFileNumber) {
        return "文件数量错误：最多只能上传"+maxFileNumber+"个文件"
    },
    /**
     * 文件已经存在
     * @param fileName 文件名
     */
    "fileIsExit" : function (fileName) {
        return "重复上传文件错误：文件（"+fileName+"）已经存在！";
    },
    /**
     * 文件已经上传过了
     * @param fileName 文件名
     */
    "fileIsHaveUpload" : function (fileName) {
        return "多次上传文件错误：文件（"+fileName+"）已经上传过，不能再次上传！";
    },
    /**
     * 文件总大小超出上传限制
     * @param totalSize 上传限制的总大小
     */
    "outOfTotalSize" : function (totalSize) {
        return "文件总大小限制错误：文件加入已超出总大小限制！请控制在"+totalSize+"KB以内，或多次上传";
    },
    /**
     * 单个文件超出大小限定
     * @param fileName 文件名字
     * @param fileSizeLimit 单个文件的限定大小
     */
    "outOfSize" : function (fileName,fileSizeLimit) {
        return "文件大小限制错误：文件（"+fileName+"）超出了大小限制！请控制在"+fileSizeLimit+"KB内"
    },
    /**
     * 不支持文件类型错误
     * @param fileName 文件名称
     */
    "notSupperFileType" : function (fileName) {
        return "文件格式错误：不支持该格式文件上传:"+fileName;
    },
    /**
     * 没有要上传的文件
     */
    "noFileUpload" : "没有要上传的文件！",
    /**
     * 没有上传的地址，进行模拟上传，不会上传，但是会进行模拟上传的过程
     */
    "noUploadUrl" : "没有上传的地址，进行模拟上传......"
};

/**
 * 解决浮点计算精度问题
 */
let WpFileUploadComputer = {
    /**
     * 加法运算
     * @param a 参数
     * @param b 参数
     */
    "add" : function (a, b) {
        let c, d, e;
        try {
            c = a.toString().split(".")[1].length;
        } catch (f) {
            c = 0;
        }
        try {
            d = b.toString().split(".")[1].length;
        } catch (f) {
            d = 0;
        }
        return e = Math.pow(10, Math.max(c, d)), (WpFileUploadComputer.mul(a, e) + WpFileUploadComputer.mul(b, e)) / e;
    },
    /**
     * 减法
     * @param a 参数
     * @param b 参数
     */
    "sub" : function (a, b) {
        let c, d, e;
        try {
            c = a.toString().split(".")[1].length;
        } catch (f) {
            c = 0;
        }
        try {
            d = b.toString().split(".")[1].length;
        } catch (f) {
            d = 0;
        }
        return e = Math.pow(10, Math.max(c, d)), (WpFileUploadComputer.mul(a, e) - WpFileUploadComputer.mul(b, e)) / e;
    },
    /**
     * 乘法
     * @param a 参数
     * @param b 参数
     */
    "mul" : function (a, b) {
        var c = 0,
            d = a.toString(),
            e = b.toString();
        try {
            c += d.split(".")[1].length;
        } catch (f) {}
        try {
            c += e.split(".")[1].length;
        } catch (f) {}
        return Number(d.replace(".", "")) * Number(e.replace(".", "")) / Math.pow(10, c);
    },
    /**
     * 除法
     * @param a 参数
     * @param b 参数
     */
    "div" : function (a, b) {
        let c, d, e = 0,
            f = 0;
        try {
            e = a.toString().split(".")[1].length;
        } catch (g) {}
        try {
            f = b.toString().split(".")[1].length;
        } catch (g) {}
        return c = Number(a.toString().replace(".", "")), d = Number(b.toString().replace(".", "")), WpFileUploadComputer.mul(c / d, Math.pow(10, f - e));
    }
};
/**
 * 文件上传工具
 * @type {{getDataWithUploadFile: (function(*): Array), getData: (function(*))}}
 */
let WpFileUploadFormTools = {
    /**
     * 获取表单json数据
     * @param formId 表单ID
     */
    "getFormData" : function(formId) {
        let formData = {};
        let $form = $("#"+formId);
        let input_doms = $form.find("input[name][ignore!='true'],textarea[name][ignore!='true']");
        let select_doms = $form.find("select[name][ignore!='true']");
        for (let i = 0; i < input_doms.length; i++) {
            let inputItem = input_doms.eq(i);
            let inputName="";
            if (inputItem.attr("type") == "radio") {
                if (inputItem.is(":checked")) {
                    inputName = inputItem.attr("name");
                    formData[inputName] = $.trim(inputItem.val());
                }
            } else {
                inputName = inputItem.attr("name");
                formData[inputName] = $.trim(inputItem.val());
            }

        }
        for (let j = 0; j < select_doms.length; j++) {
            let selectItem = select_doms.eq(j);
            let selectName = selectItem.attr("name");
            formData[selectName] = $.trim(selectItem.val());
        }
        return formData;
    },
    /**
     * 获取表单json数据,以上传文件的形式封装
     * @param formId 表单ID
     * @returns {Array} 表单数据
     */
    "getFormDataOfUploadFile" : function(formId) {
        let formData = [];
        let $form = $("#"+formId);
        let input_doms = $form.find("input[name][ignore!='true'],textarea[name][ignore!='true']");
        let select_doms = $form.find("select[name][ignore!='true']");
        for (let i = 0; i < input_doms.length; i++) {
            let inputItem = input_doms.eq(i);
            let inputName="";
            if (inputItem.attr("type") == "radio") {
                if (inputItem.is(":checked")) {
                    inputName = inputItem.attr("name");
                    formData[formData.length] = {"name":inputName, "value":$.trim(inputItem.val())}
                }
            }else{
                inputName = inputItem.attr("name");
                formData[formData.length] = {"name":inputName, "value":$.trim(inputItem.val())}
            }
        }
        for (let j = 0; j < select_doms.length; j++) {
            let selectItem = select_doms.eq(j);
            let selectName = selectItem.attr("name");
            formData[formData.length] = {"name":selectName, "value":$.trim(selectItem.val())}
        }
        return formData;
    }
};
/**
 * 获取文件视图对象工具
 * @type {{getNeedUploadItemArray: WpFileItemTools.getNeedUploadItemArray, getIsUploadItemArray: WpFileItemTools.getIsUploadItemArray, getNeedUploadItem: WpFileItemTools.getNeedUploadItem, getIsUploadItem: WpFileItemTools.getIsUploadItem}}
 */
let WpFileItemTools  = {
    /**
     * 获取需要上传的文件对象数组
     * @param uploadId 文件上传Id
     */
    "getNeedUploadItemArray" : function (uploadId) {
        return $("#"+uploadId+" .box .fileItem[fileCodeId]");
    },
    /**
     * 获取已经上传的文件对象数组
     * @param uploadId 文件上传Id
     */
    "getIsUploadItemArray" : function (uploadId) {
        return $("#"+uploadId+" .box .fileItem[showFileCode]");
    },
    /**
     * 获取某个需要上传的文件对象
     * @param uploadId 文件上传Id
     * @param fileId 文件编号Id
     */
    "getNeedUploadItem" : function (uploadId, fileId) {
        return $("#"+uploadId+" .box .fileItem[fileCodeId='" + fileId + "']");
    },
    /**
     * 获取某个已经上传的文件对象
     * @param uploadId 文件上传Id
     * @param fileId 文件编号Id
     */
    "getIsUploadItem" : function (uploadId, fileId) {
        return $("#"+uploadId+" .box .fileItem[showFileCode='" + fileId + "']");
    },
    /**
     * 获取所示所有文件的视图单例对象,这里不区分是否已经上传
     * @param uploadId 文件上传Id
     */
    "getFileViewArray" : function (uploadId){
        return $("#"+uploadId+" .box .fileItem");
    },
    /**
     * 获取文件视图进度条
     * @param viewObj 获取到的文件视图对象，注意是jquery对象
     */
    "getFileViewProgress" : function (viewObj) {
        return viewObj.find(".progress>div");
    },
    /**
     * 获取文件视图状态标识
     * @param viewObj 获取到的文件视图对象，注意是jquery对象
     */
    "getFileViewStatus" : function (viewObj) {
        return viewObj.find(".status>i");
    },
    /**
     * 获取文件视图状态删除的按钮
     * @param viewObj 获取到的文件视图对象，注意是jquery对象
     */
    "getFileViewRemove" : function(viewObj) {
        return viewObj.find(".status .icon-shanchu");
    },
    /**
     * 获取文件下载按钮
     * @param viewObj 获取到的文件视图对象，注意是jquery对象
     */
    "getFileViewDown" : function (viewObj) {
        return viewObj.find(".down .icon-xiazai");
    }
};