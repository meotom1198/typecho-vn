<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$stat = \Widget\Stat::alloc();
$comments = \Widget\Comments\Admin::alloc();
$isAllComments = ('on' == $request->get('__typecho_all_comments') || 'on' == \Typecho\Cookie::get('__typecho_all_comments'));
?>
<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 typecho-list">
                <div class="clearfix">
                    <ul class="typecho-option-tabs right">
                    <?php if($user->pass('editor', true) && !isset($request->cid)): ?>
                        <li class="<?php if($isAllComments): ?> current<?php endif; ?>"><a href="<?php echo $request->makeUriByRequest('__typecho_all_comments=on'); ?>"><?php _e('Tất cả'); ?></a></li>
                        <li class="<?php if(!$isAllComments): ?> current<?php endif; ?>"><a href="<?php echo $request->makeUriByRequest('__typecho_all_comments=off'); ?>"><?php _e('Của tôi'); ?></a></li>
                    <?php endif; ?>
                    </ul>
                    <ul class="typecho-option-tabs">
                        <li<?php if(!isset($request->status) || 'approved' == $request->get('status')): ?> class="current"<?php endif; ?>><a href="<?php $options->adminUrl('manage-comments.php'
                        . (isset($request->cid) ? '?cid=' . $request->filter('encode')->cid : '')); ?>"><?php _e('Duyệt'); ?></a></li>
                        <li<?php if('waiting' == $request->get('status')): ?> class="current"<?php endif; ?>><a href="<?php $options->adminUrl('manage-comments.php?status=waiting'
                        . (isset($request->cid) ? '&cid=' . $request->filter('encode')->cid : '')); ?>"><?php _e('Chờ duyệt'); ?>
                        <?php if(!$isAllComments && $stat->myWaitingCommentsNum > 0 && !isset($request->cid)): ?> 
                            <span class="balloon"><?php $stat->myWaitingCommentsNum(); ?></span>
                        <?php elseif($isAllComments && $stat->waitingCommentsNum > 0 && !isset($request->cid)): ?>
                            <span class="balloon"><?php $stat->waitingCommentsNum(); ?></span>
                        <?php elseif(isset($request->cid) && $stat->currentWaitingCommentsNum > 0): ?>
                            <span class="balloon"><?php $stat->currentWaitingCommentsNum(); ?></span>
                        <?php endif; ?>
                        </a></li>
                        <li<?php if('spam' == $request->get('status')): ?> class="current"<?php endif; ?>><a href="<?php $options->adminUrl('manage-comments.php?status=spam'
                        . (isset($request->cid) ? '&cid=' . $request->filter('encode')->cid : '')); ?>"><?php _e('Spam'); ?>
                        <?php if(!$isAllComments && $stat->mySpamCommentsNum > 0 && !isset($request->cid)): ?> 
                            <span class="balloon"><?php $stat->mySpamCommentsNum(); ?></span>
                        <?php elseif($isAllComments && $stat->spamCommentsNum > 0 && !isset($request->cid)): ?>
                            <span class="balloon"><?php $stat->spamCommentsNum(); ?></span>
                        <?php elseif(isset($request->cid) && $stat->currentSpamCommentsNum > 0): ?>
                            <span class="balloon"><?php $stat->currentSpamCommentsNum(); ?></span>
                        <?php endif; ?>
                        </a></li>
                    </ul>
                </div>
            
                <div class="typecho-list-operate clearfix">
                    <form method="get">
                        <div class="operate">
                            <label><i class="sr-only"><?php _e('Chọn tất cả'); ?></i><input type="checkbox" class="typecho-table-select-all" /></label>
                            <div class="btn-group btn-drop">
                            <button class="btn dropdown-toggle btn-s" type="button"><i class="sr-only"><?php _e('Hành động'); ?></i><?php _e('Mục đã chọn'); ?> <i class="i-caret-down"></i></button>
                            <ul class="dropdown-menu">
                                <li><a href="<?php $security->index('/action/comments-edit?do=approved'); ?>"><?php _e('Duyệt'); ?></a></li>
                                <li><a href="<?php $security->index('/action/comments-edit?do=waiting'); ?>"><?php _e('Chờ duyệt'); ?></a></li>
                                <li><a href="<?php $security->index('/action/comments-edit?do=spam'); ?>"><?php _e('Spam'); ?></a></li>
                                <li><a lang="<?php _e('Bạn có chắc chắn muốn xóa những bình luận này?'); ?>" href="<?php $security->index('/action/comments-edit?do=delete'); ?>"><?php _e('Xóa bỏ'); ?></a></li>
                            </ul>
                            <?php if('spam' == $request->get('status')): ?>
                                <button lang="<?php _e('Bạn có chắc chắn muốn xóa tất cả bình luận spam không?'); ?>" class="btn btn-s btn-warn btn-operate" href="<?php $security->index('/action/comments-edit?do=delete-spam'); ?>"><?php _e('Xóa bỏ'); ?></button>
                            <?php endif; ?>
                            </div>
                        </div>
                        <div class="search" role="search">
                            <?php if ('' != $request->keywords || '' != $request->category): ?>
                            <a href="<?php $options->adminUrl('manage-comments.php' 
                            . (isset($request->status) || isset($request->cid) ? '?' .
                            (isset($request->status) ? 'status=' . $request->filter('encode')->status : '') .
                            (isset($request->cid) ? (isset($request->status) ? '&' : '') . 'cid=' . $request->filter('encode')->cid : '') : '')); ?>"><?php _e('&laquo; Hủy bộ lọc'); ?></a>
                            <?php endif; ?>
                            <input type="text" class="text-s" placeholder="<?php _e('Vui lòng nhập từ khóa'); ?>" value="<?php echo $request->filter('html')->keywords; ?>"<?php if ('' == $request->keywords): ?> onclick="value='';name='keywords';" <?php else: ?> name="keywords"<?php endif; ?>/>
                            <?php if(isset($request->status)): ?>
                                <input type="hidden" value="<?php echo $request->filter('html')->status; ?>" name="status" />
                            <?php endif; ?>
                            <?php if(isset($request->cid)): ?>
                                <input type="hidden" value="<?php echo $request->filter('html')->cid; ?>" name="cid" />
                            <?php endif; ?>
                            <button type="submit" class="btn btn-s"><?php _e('Lọc'); ?></button>
                        </div>
                    </form>
                </div><!-- end .typecho-list-operate -->
                
                <form method="post" name="manage_comments" class="operate-form">
                <div class="typecho-table-wrap">
                    <table class="typecho-list-table">
                        <colgroup>
                            <col width="3%" class="kit-hidden-mb"/>
                            <col width="6%" class="kit-hidden-mb" />
                            <col width="20%"/>
                            <col width="71%"/>
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="kit-hidden-mb"> </th>
                                <th><?php _e('Tác giả'); ?></th>
                                <th class="kit-hidden-mb"> </th>
                                <th><?php _e('Nội dung'); ?></th>
                            </tr>
                        </thead>
                        <tbody>

                        <?php if($comments->have()): ?>
                        <?php while($comments->next()): ?>
                        <tr id="<?php $comments->theId(); ?>" data-comment="<?php
                        $comment = array(
                            'author'    =>  $comments->author,
                            'mail'      =>  $comments->mail,
                            'url'       =>  $comments->url,
                            'ip'        =>  $comments->ip,
                            'type'        =>  $comments->type,
                            'text'      =>  $comments->text
                        );

                        echo htmlspecialchars(json_encode($comment));
                        ?>">
                            <td valign="top" class="kit-hidden-mb">
                                <input type="checkbox" value="<?php $comments->coid(); ?>" name="coid[]"/>
                            </td>
                            <td valign="top" class="kit-hidden-mb">
                                <div class="comment-avatar">
                                    <?php if ('comment' == $comments->type): ?>
                                    <?php $comments->gravatar(40); ?>
                                    <?php endif; ?>
                                    <?php if ('comment' != $comments->type): ?>
                                    <?php _e('Trích dẫn'); ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td valign="top" class="comment-head">
                                <div class="comment-meta">
                                    <i class="fa fa-user" aria-hidden="true"></i> <strong class="comment-author"><?php $comments->author(true); ?></strong>
                                    <?php if($comments->mail): ?>
                                    <br /><span><a href="<?php $comments->mail(true); ?>"><?php $comments->mail(); ?></a></span>
                                    <?php endif; ?>
                                    <?php if($comments->ip): ?>
                                    <br /><span><?php $comments->ip(); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td valign="top" class="comment-body">
                                <div class="comment-date"><i class="fa fa-clock-o" aria-hidden="true"></i> <?php $comments->dateWord(); ?> | Tại bài viết: <a href="<?php $comments->permalink(); ?>"><?php $comments->title(); ?></a></div>
                                <div class="comment-content">
                                    <?php $comments->content(); ?>
                                </div> 
                                <div class="comment-action hidden-by-mouse">
                                    <?php if('approved' == $comments->status): ?>
                                    <span class="weak"><i class="fa fa-check" aria-hidden="true"></i> <?php _e('Duyệt'); ?></span>
                                    <?php else: ?>
                                    <i class="fa fa-check" aria-hidden="true"></i> <a href="<?php $security->index('/action/comments-edit?do=approved&coid=' . $comments->coid); ?>" class="operate-approved"><?php _e('Duyệt'); ?></a>
                                    <?php endif; ?>
                                    
                                    <?php if('waiting' == $comments->status): ?>
                                    <span class="weak"><i class="fa fa-spinner" aria-hidden="true"></i> <?php _e('Chờ duyệt'); ?></span>
                                    <?php else: ?>
                                    <i class="fa fa-spinner" aria-hidden="true"></i> <a href="<?php $security->index('/action/comments-edit?do=waiting&coid=' . $comments->coid); ?>" class="operate-waiting"><?php _e('Chờ duyệt'); ?></a>
                                    <?php endif; ?>
                                    
                                    <?php if('spam' == $comments->status): ?>
                                    <span class="weak"><i class="fa fa-times-circle" aria-hidden="true"></i> <?php _e('Spam'); ?></span>
                                    <?php else: ?>
                                    <i class="fa fa-times-circle" aria-hidden="true"></i> <a href="<?php $security->index('/action/comments-edit?do=spam&coid=' . $comments->coid); ?>" class="operate-spam"><?php _e('Spam'); ?></a>
                                    <?php endif; ?>
                                    
                                    <i class="fa fa-pencil" aria-hidden="true"></i> <a href="#<?php $comments->theId(); ?>" rel="<?php $security->index('/action/comments-edit?do=edit&coid=' . $comments->coid); ?>" class="operate-edit"><?php _e('Sửa'); ?></a>

                                    <?php if('approved' == $comments->status && 'comment' == $comments->type): ?>
                                    <i class="fa fa-quote-right" aria-hidden="true"></i> <a href="#<?php $comments->theId(); ?>" rel="<?php $security->index('/action/comments-edit?do=reply&coid=' . $comments->coid); ?>" class="operate-reply"><?php _e('Trả lời'); ?></a>
                                    <?php endif; ?>
                                    
                                    <i class="fa fa-trash-o" aria-hidden="true"></i> <a lang="<?php _e('Bạn có chắc chắn muốn xóa bình luận của %s không?', htmlspecialchars($comments->author)); ?>" href="<?php $security->index('/action/comments-edit?do=delete&coid=' . $comments->coid); ?>" class="operate-delete"><?php _e('Xóa bỏ'); ?></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="4"><h6 class="typecho-list-table-title"><?php _e('Chưa có bình luận!') ?></h6></td>
                        </tr>
                        <?php endif; ?>
                        </tbody>
                    </table><!-- end .typecho-list-table -->
                </div><!-- end .typecho-table-wrap -->

                <?php if(isset($request->cid)): ?>
                <input type="hidden" value="<?php echo $request->filter('html')->cid; ?>" name="cid" />
                <?php endif; ?>
                </form><!-- end .operate-form -->

                <div class="typecho-list-operate clearfix">
                    <form method="get">
                        <div class="operate">
                            <label><i class="sr-only"><?php _e('Chọn tất cả'); ?></i><input type="checkbox" class="typecho-table-select-all" /></label>
                            <div class="btn-group btn-drop">
                            <button class="btn dropdown-toggle btn-s" type="button"><i class="sr-only"><?php _e('Hành động'); ?></i><?php _e('Mục đã chọn'); ?> <i class="i-caret-down"></i></button>
                            <ul class="dropdown-menu">
                                <li><a href="<?php $security->index('/action/comments-edit?do=approved'); ?>"><?php _e('Duyệt'); ?></a></li>
                                <li><a href="<?php $security->index('/action/comments-edit?do=waiting'); ?>"><?php _e('Chờ duyệt'); ?></a></li>
                                <li><a href="<?php $security->index('/action/comments-edit?do=spam'); ?>"><?php _e('Spam'); ?></a></li>
                                <li><a lang="<?php _e('Bạn có chắc chắn muốn xóa những bình luận này?'); ?>" href="<?php $security->index('/action/comments-edit?do=delete'); ?>"><?php _e('Xóa bỏ'); ?></a></li>
                            </ul>
                            <?php if('spam' == $request->get('status')): ?>
                                <button lang="<?php _e('Bạn có chắc chắn muốn xóa tất cả bình luận spam không?'); ?>" class="btn btn-s btn-warn btn-operate" href="<?php $security->index('/action/comments-edit?do=delete-spam'); ?>"><?php _e('Xóa bỏ'); ?></button>
                            <?php endif; ?>
                            </div>
                        </div>
                        <?php if($comments->have()): ?>
                        <ul class="typecho-pager">
                            <?php $comments->pageNav(); ?>
                        </ul>
                        <?php endif; ?>
                    </form>
                </div><!-- end .typecho-list-operate -->
            </div><!-- end .typecho-list -->
        </div><!-- end .typecho-page-main -->
    </div>
</div>
<?php
include 'copyright.php';
include 'common-js.php';
include 'table-js.php';
?>
<script type="text/javascript">
$(document).ready(function () {
    // Ghi nhớ thanh cuộn
    function rememberScroll () {
        $(window).bind('beforeunload', function () {
            $.cookie('__typecho_comments_scroll', $('body').scrollTop());
        });
    }

    // cuộn tự động
    (function () {
        var scroll = $.cookie('__typecho_comments_scroll');

        if (scroll) {
            $.cookie('__typecho_comments_scroll', null);
            $('html, body').scrollTop(scroll);
        }
    })();

    $('.operate-delete').click(function () {
        var t = $(this), href = t.attr('href'), tr = t.parents('tr');

        if (confirm(t.attr('lang'))) {
            tr.fadeOut(function () {
                rememberScroll();
                window.location.href = href;
            });
        }

        return false;
    });

    $('.operate-approved, .operate-waiting, .operate-spam').click(function () {
        rememberScroll();
        window.location.href = $(this).attr('href');
        return false;
    });

    $('.operate-reply').click(function () {
        var td = $(this).parents('td'), t = $(this);

        if ($('.comment-reply', td).length > 0) {
            $('.comment-reply').remove();
        } else {
            var form = $('<form method="post" action="'
                + t.attr('rel') + '" class="comment-reply">'
                + '<p><label for="text" class="sr-only"><?php _e('Nội dung'); ?></label><textarea id="text" name="text" class="w-90 mono" rows="3"></textarea></p>'
                + '<p><button type="submit" class="btn btn-s primary"><?php _e('Trả lời'); ?></button> <button type="button" class="btn btn-s cancel"><?php _e('Hủy bỏ'); ?></button></p>'
                + '</form>').insertBefore($('.comment-action', td));

            $('.cancel', form).click(function () {
                $(this).parents('.comment-reply').remove();
            });

            var textarea = $('textarea', form).focus();

            form.submit(function () {
                var t = $(this), tr = t.parents('tr'), 
                    reply = $('<div class="comment-reply-content"></div>').insertAfter($('.comment-content', tr));

                var html = DOMPurify.sanitize(textarea.val(), {USE_PROFILES: {html: true}});
                reply.html('<p>' + html + '</p>');
                $.post(t.attr('action'), t.serialize(), function (o) {
                    var html = DOMPurify.sanitize(o.comment.content, {USE_PROFILES: {html: true}});
                    reply.html(html)
                        .effect('highlight');
                }, 'json');

                t.remove();
                return false;
            });
        }

        return false;
    });

    $('.operate-edit').click(function () {
        var tr = $(this).parents('tr'), t = $(this), id = tr.attr('id'), comment = tr.data('comment');
        tr.hide();

        var edit = $('<tr class="comment-edit"><td> </td>'
                        + '<td colspan="2" valign="top"><form method="post" action="'
                        + t.attr('rel') + '" class="comment-edit-info">'
                        + '<p><label for="' + id + '-author"><?php _e('Tên tài khoản'); ?></label><input class="text-s w-100" id="'
                        + id + '-author" name="author" type="text"></p>'
                        + '<p><label for="' + id + '-mail"><?php _e('E-mail'); ?></label>'
                        + '<input class="text-s w-100" type="email" name="mail" id="' + id + '-mail"></p>'
                        + '<p><label for="' + id + '-url"><?php _e('Trang cá nhân'); ?></label>'
                        + '<input class="text-s w-100" type="text" name="url" id="' + id + '-url"></p></form></td>'
                        + '<td valign="top"><form method="post" action="'
                        + t.attr('rel') + '" class="comment-edit-content"><p><label for="' + id + '-text"><?php _e('Nội dung'); ?></label>'
                        + '<textarea name="text" id="' + id + '-text" rows="6" class="w-90 mono"></textarea></p>'
                        + '<p><button type="submit" class="btn btn-s primary"><?php _e('Gửi'); ?></button> '
                        + '<button type="button" class="btn btn-s cancel"><?php _e('Hủy bỏ'); ?></button></p></form></td></tr>')
                        .data('id', id).data('comment', comment).insertAfter(tr);

        $('input[name=author]', edit).val(comment.author);
        $('input[name=mail]', edit).val(comment.mail);
        $('input[name=url]', edit).val(comment.url);
        $('textarea[name=text]', edit).val(comment.text).focus();

        $('.cancel', edit).click(function () {
            var tr = $(this).parents('tr');

            $('#' + tr.data('id')).show();
            tr.remove();
        });

        $('form', edit).submit(function () {
            var t = $(this), tr = t.parents('tr'),
                oldTr = $('#' + tr.data('id')),
                comment = oldTr.data('comment');

            $('form', tr).each(function () {
                var items  = $(this).serializeArray();

                for (var i = 0; i < items.length; i ++) {
                    var item = items[i];
                    comment[item.name] = item.value;
                }
            });

            var unsafeHTML = '<strong class="comment-author">'
                + (comment.url ? '<a target="_blank" href="' + comment.url + '">'
                + comment.author + '</a>' : comment.author) + '</strong>'
                + ('comment' != comment.type ? '<small><?php _e('Trích dẫn'); ?></small>' : '')
                + (comment.mail ? '<br /><span><a href="mailto:' + comment.mail + '">'
                + comment.mail + '</a></span>' : '')
                + (comment.ip ? '<br /><span>' + comment.ip + '</span>' : '');

            var html = DOMPurify.sanitize(unsafeHTML, {USE_PROFILES: {html: true}});
            var content = DOMPurify.sanitize(comment.text, {USE_PROFILES: {html: true}});
            $('.comment-meta', oldTr).html(html)
                .effect('highlight');
            $('.comment-content', oldTr).html('<p>' + content + '</p>');
            oldTr.data('comment', comment);

            $.post(t.attr('action'), comment, function (o) {
                var content = DOMPurify.sanitize(o.comment.content, {USE_PROFILES: {html: true}});
                $('.comment-content', oldTr).html('<p>' + content + '</p>')
                    .effect('highlight');
            }, 'json');
            
            oldTr.show();
            tr.remove();

            return false;
        });

        return false;
    });
});
</script>
<?php
include 'footer.php';
?>
