

$(document).ready(function () {

    i_note_activate();

    set_autosize($('#x_reply'));

    //Watchout for file uplods:
    $('.boxUpload').find('input[type="file"]').change(function () {
        x_upload(droppedFiles, 'file');
    });

    //Should we auto start?
    if (isAdvancedUpload) {

        $('.boxUpload').addClass('has-advanced-upload');
        var droppedFiles = false;

        $('.boxboxUpload').on('drag dragstart dragend dragover dragenter dragleave drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
        })
        .on('dragover dragenter', function () {
            $('.userUploader').addClass('dynamic_saving');
        })
        .on('dragleave dragend drop', function () {
            $('.userUploader').removeClass('dynamic_saving');
        })
        .on('drop', function (e) {
            droppedFiles = e.originalEvent.dataTransfer.files;
            e.preventDefault();
            x_upload(droppedFiles, 'drop');
        });
    }

});

function go_next(go_next_url){

    //Attempts to go next if no submissions:
    if(focus_i__type==6683) {

        //TEXT RESPONSE:
        return x_reply(go_next_url);

    } else if (js_n___7712.includes(focus_i__type) && $('.list-answers .answer-item').length){

        //SELECT ONE/SOME
        return x_select(go_next_url);

    } else if (focus_i__type==7637 && !($('.file_saving_result').html().length) ) {

        //Must upload file first:
        alert('You must upload file before going next.');

    } else {

        if(go_next_url && go_next_url.length > 0){

            //Go Next:
            $('.go-next').html(( js_pl_id > 0 ? '<i class="fas fa-check-circle"></i>' : '<i class="far fa-yin-yang fa-spin"></i>' ));
            window.location = go_next_url;

        } else {

            x_create({
                x__source: js_pl_id,
                x__type: 14604, //START BLOCKED
                x__left: $('#focus__id').val(),
            });

            if(superpower_js_10939){
                alert('This idea is not published yet & cannot be started unless you add it to MY IDEAS and then you can start & preview it while it is reviewed and published by a moderator.');
            } else {
                alert('This idea is not published yet & cannot be started at this time.');
            }

        }
    }
}

function load_comments(){
    if($('.view-discussions').hasClass('hidden')){
        $('.view-discussions').removeClass('hidden');
        $('.input_note_12419').focus();
        setTimeout(function () {
            $('.input_note_12419').focus();
        }, 144);
    } else {
        $('.view-discussions').addClass('hidden');
    }

}

function select_answer(i__id){

    //Allow answer to be saved/updated:
    var i__type = parseInt($('.list-answers').attr('i__type'));

    //Clear all if single selection:
    if(i__type == 6684){
        //Single Selection, clear all:
        $('.item-selected').addClass('hidden');
        $('.answer-item').removeClass('coinType12273');
    }

    //Is setected?
    if(!($('.x_select_'+i__id+' .item-selected').hasClass('hidden'))){

        //Previously Selected, delete selection:
        if(i__type == 7231 || i__type == 14861){
            //Multi Selection
            $('.x_select_'+i__id+' .item-selected').addClass('hidden');
            $('.x_select_'+i__id).removeClass('coinType12273');
        }

    } else {

        //Not selected, select now:
        $('.x_select_'+i__id+' .item-selected').removeClass('hidden');
        $('.x_select_'+i__id).addClass('coinType12273');

    }

}


function x_upload(droppedFiles, uploadType) {

    //Prevent multiple concurrent uploads:
    if ($('.boxUpload').hasClass('dynamic_saving')) {
        return false;
    }

    $('.file_saving_result').html('<span class="icon-block"><i class="far fa-yin-yang fa-spin"></i></span><span class="css__title">UPLOADING...</span>');

    if (isAdvancedUpload) {

        var ajaxData = new FormData($('.boxUpload').get(0));
        if (droppedFiles) {
            $.each(droppedFiles, function (i, file) {
                var thename = $('.boxUpload').find('input[type="file"]').attr('name');
                if (typeof thename == typeof undefined || thename == false) {
                    var thename = 'drop';
                }
                ajaxData.append(uploadType, file);
            });
        }

        ajaxData.append('upload_type', uploadType);
        ajaxData.append('i__id', $('#focus__id').val());
        ajaxData.append('top_i__id', $('#top_i__id').val());

        $.ajax({
            url: '/x/x_upload',
            type: $('.boxUpload').attr('method'),
            data: ajaxData,
            dataType: 'json',
            cache: false,
            contentType: false,
            processData: false,
            complete: function () {
                $('.boxUpload').removeClass('dynamic_saving');
            },
            success: function (data) {
                //Render new file:
                $('.file_saving_result').html(data.message);
                $('.go_next_upload').removeClass('hidden');
                lazy_load();
            },
            error: function (data) {
                //Show Error:
                $('.file_saving_result').html(data.responseText);
            }
        });
    } else {
        // ajax for legacy browsers
    }

}


function x_reply(go_next_url){
    $.post("/x/x_reply", {
        i__id:$('#focus__id').val(),
        top_i__id:$('#top_i__id').val(),
        x_reply:$('#x_reply').val(),
    }, function (data) {
        if (data.status) {
            //Go to redirect message:
            $('.go-next').html('<i class="fas fa-check-circle"></i>');
            window.location = go_next_url;
        } else {
            //Show error:
            alert(data.message);
        }
    });
}

function x_select(go_next_url){

    //Check
    var selection_i__id = [];
    $(".answer-item").each(function () {
        var selection_i__id_this = parseInt($(this).attr('selection_i__id'));
        if (!($('.x_select_'+selection_i__id_this+' .item-selected').hasClass('hidden'))) {
            selection_i__id.push(selection_i__id_this);
        }
    });


    //Show Loading:
    $.post("/x/x_select", {
        focus_i__type:focus_i__type,
        focus__id:$('#focus__id').val(),
        top_i__id:$('#top_i__id').val(),
        selection_i__id:selection_i__id
    }, function (data) {
        if (data.status) {
            //Go to redirect message:
            $('.go-next').html('<i class="fas fa-check-circle"></i>');
            window.location = go_next_url;
        } else {
            //Show error:
            alert(data.message);
        }
    });
}

