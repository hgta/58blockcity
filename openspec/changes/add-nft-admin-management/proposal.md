# NFT 管理后台

## 问题
nft/admin 仅有 dashboard + appeal_review 两个页面，缺少 NFT CRUD、标签管理、评论管理。

## 目标
1. NFT 列表管理（搜索/编辑/删除）
2. NFT 创建/编辑 + 标签关联
3. 标签管理 CRUD
4. 菜单增加对应入口

## 改动
- `classes/NFT.php`: createNft/updateNft/deleteNft/setNftTags + 标签 CRUD
- `nft/admin/nfts.php` `nft/admin/nft_form.php` `nft/admin/tags.php`
- `shared/admin/admin-menu-config.php` 菜单加 NFT管理 + 标签管理
