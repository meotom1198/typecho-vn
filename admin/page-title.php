<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<div class="typecho-page-title">
    <h2><?php echo $menu->title; ?><?php 
    if (!empty($menu->addLink)) {
        echo "<a href=\"{$menu->addLink}\">" . _t("Tạo thêm") . "</a>";
    }
    ?></h2>
</div>
