<?php

require_once  '../../config/database.php';

// 设置PHP头部确保UTF-8输出
header('Content-Type: text/html; charset=utf-8');

// 确保连接使用utf8mb4
$pdo->exec("SET NAMES utf8mb4");

// 城市数据初始化
function initCities($pdo) {
    $cities = [
    ['name' => '北京', 'area_code' => '010', 'rank' => 1, 'resident_count' => 2368, 'activated_blocks' => 3007],
    ['name' => '杭州', 'area_code' => '0571', 'rank' => 2, 'resident_count' => 1887, 'activated_blocks' => 2447],
    ['name' => '深圳', 'area_code' => '0755', 'rank' => 3, 'resident_count' => 1369, 'activated_blocks' => 1944],
    ['name' => '上海', 'area_code' => '021', 'rank' => 4, 'resident_count' => 1339, 'activated_blocks' => 1787],
    ['name' => '中国数藏', 'area_code' => 'uijd', 'rank' => 5, 'resident_count' => 1290, 'activated_blocks' => 1711],
    ['name' => '广州', 'area_code' => '020', 'rank' => 6, 'resident_count' => 966, 'activated_blocks' => 1326],
    ['name' => '成都', 'area_code' => '028', 'rank' => 7, 'resident_count' => 915, 'activated_blocks' => 1218],
    ['name' => '重庆', 'area_code' => '023', 'rank' => 8, 'resident_count' => 548, 'activated_blocks' => 776],
    ['name' => '天津', 'area_code' => '022', 'rank' => 9, 'resident_count' => 541, 'activated_blocks' => 716],
    ['name' => '苏州', 'area_code' => '0512', 'rank' => 10, 'resident_count' => 491, 'activated_blocks' => 695],
    ['name' => '西安', 'area_code' => '029', 'rank' => 11, 'resident_count' => 512, 'activated_blocks' => 677],
    ['name' => '太原', 'area_code' => '0351', 'rank' => 12, 'resident_count' => 493, 'activated_blocks' => 647],
    ['name' => '合肥', 'area_code' => '0551', 'rank' => 13, 'resident_count' => 470, 'activated_blocks' => 640],
    ['name' => '中国书画', 'area_code' => 'sojs', 'rank' => 14, 'resident_count' => 386, 'activated_blocks' => 639],
    ['name' => '武汉', 'area_code' => '027', 'rank' => 15, 'resident_count' => 453, 'activated_blocks' => 633],
    ['name' => '南京', 'area_code' => '025', 'rank' => 16, 'resident_count' => 439, 'activated_blocks' => 618],
    ['name' => '济南', 'area_code' => '0531', 'rank' => 17, 'resident_count' => 486, 'activated_blocks' => 615],
    ['name' => '长沙', 'area_code' => '0731', 'rank' => 18, 'resident_count' => 425, 'activated_blocks' => 604],
    ['name' => '宁波', 'area_code' => '0574', 'rank' => 19, 'resident_count' => 412, 'activated_blocks' => 604],
    ['name' => '青岛', 'area_code' => '0532', 'rank' => 20, 'resident_count' => 421, 'activated_blocks' => 601],
    ['name' => '贵阳', 'area_code' => '0851', 'rank' => 21, 'resident_count' => 481, 'activated_blocks' => 596],
    ['name' => '郑州', 'area_code' => '0371', 'rank' => 22, 'resident_count' => 417, 'activated_blocks' => 596],
    ['name' => '惠州', 'area_code' => '0752', 'rank' => 23, 'resident_count' => 421, 'activated_blocks' => 589],
    ['name' => '昆明', 'area_code' => '0871', 'rank' => 24, 'resident_count' => 416, 'activated_blocks' => 567],
    ['name' => '沈阳', 'area_code' => '024', 'rank' => 25, 'resident_count' => 397, 'activated_blocks' => 549],
    ['name' => '金华', 'area_code' => '0579', 'rank' => 26, 'resident_count' => 360, 'activated_blocks' => 547],
    ['name' => '无锡', 'area_code' => '0510', 'rank' => 27, 'resident_count' => 380, 'activated_blocks' => 527],
    ['name' => '厦门', 'area_code' => '0592', 'rank' => 28, 'resident_count' => 366, 'activated_blocks' => 515],
    ['name' => '周口', 'area_code' => '0394', 'rank' => 29, 'resident_count' => 418, 'activated_blocks' => 514],
    ['name' => '东莞', 'area_code' => '0769', 'rank' => 30, 'resident_count' => 382, 'activated_blocks' => 513],
    ['name' => '烟台', 'area_code' => '0535', 'rank' => 31, 'resident_count' => 334, 'activated_blocks' => 508],
    ['name' => '海口', 'area_code' => '0898', 'rank' => 32, 'resident_count' => 351, 'activated_blocks' => 506],
    ['name' => '宁德', 'area_code' => '0593', 'rank' => 33, 'resident_count' => 359, 'activated_blocks' => 502],
    ['name' => '珠海', 'area_code' => '0756', 'rank' => 34, 'resident_count' => 354, 'activated_blocks' => 500],
    ['name' => '福州', 'area_code' => '0591', 'rank' => 35, 'resident_count' => 354, 'activated_blocks' => 498],
    ['name' => '枣庄', 'area_code' => '0632', 'rank' => 36, 'resident_count' => 293, 'activated_blocks' => 497],
    ['name' => '常州', 'area_code' => '0519', 'rank' => 37, 'resident_count' => 346, 'activated_blocks' => 496],
    ['name' => '湖州', 'area_code' => '0572', 'rank' => 38, 'resident_count' => 353, 'activated_blocks' => 486],
    ['name' => '佛山', 'area_code' => '0757', 'rank' => 39, 'resident_count' => 342, 'activated_blocks' => 482],
    ['name' => '南昌', 'area_code' => '0791', 'rank' => 40, 'resident_count' => 363, 'activated_blocks' => 479],
    ['name' => '肇庆', 'area_code' => '0758', 'rank' => 41, 'resident_count' => 328, 'activated_blocks' => 478],
    ['name' => '嘉兴', 'area_code' => '0573', 'rank' => 42, 'resident_count' => 331, 'activated_blocks' => 471],
    ['name' => '温州', 'area_code' => '0577', 'rank' => 43, 'resident_count' => 312, 'activated_blocks' => 463],
    ['name' => '南宁', 'area_code' => '0771', 'rank' => 44, 'resident_count' => 337, 'activated_blocks' => 461],
    ['name' => '绍兴', 'area_code' => '0575', 'rank' => 45, 'resident_count' => 299, 'activated_blocks' => 459],
    ['name' => '舟山', 'area_code' => '0580', 'rank' => 46, 'resident_count' => 325, 'activated_blocks' => 449],
    ['name' => '石家庄', 'area_code' => '0311', 'rank' => 47, 'resident_count' => 315, 'activated_blocks' => 441],
    ['name' => '哈尔滨', 'area_code' => '0451', 'rank' => 48, 'resident_count' => 330, 'activated_blocks' => 440],
    ['name' => '潮州', 'area_code' => '0768', 'rank' => 49, 'resident_count' => 283, 'activated_blocks' => 432],
    ['name' => '中山', 'area_code' => '0760', 'rank' => 50, 'resident_count' => 294, 'activated_blocks' => 429],
    ['name' => '乌鲁木齐', 'area_code' => '0991', 'rank' => 51, 'resident_count' => 296, 'activated_blocks' => 429],
    ['name' => '藏南', 'area_code' => 'eaig', 'rank' => 52, 'resident_count' => 231, 'activated_blocks' => 427],
    ['name' => '长春', 'area_code' => '0431', 'rank' => 53, 'resident_count' => 316, 'activated_blocks' => 416],
    ['name' => '安顺', 'area_code' => '0853', 'rank' => 54, 'resident_count' => 363, 'activated_blocks' => 408],
    ['name' => '洛阳', 'area_code' => '0379', 'rank' => 55, 'resident_count' => 294, 'activated_blocks' => 406],
    ['name' => '大连', 'area_code' => '0411', 'rank' => 56, 'resident_count' => 282, 'activated_blocks' => 405],
    ['name' => '徐州', 'area_code' => '0516', 'rank' => 57, 'resident_count' => 273, 'activated_blocks' => 403],
    ['name' => '济宁', 'area_code' => '0537', 'rank' => 58, 'resident_count' => 273, 'activated_blocks' => 403],
    ['name' => '云浮', 'area_code' => '0766', 'rank' => 59, 'resident_count' => 315, 'activated_blocks' => 399],
    ['name' => '泉州', 'area_code' => '0595', 'rank' => 60, 'resident_count' => 237, 'activated_blocks' => 389],
    ['name' => '临沂', 'area_code' => '0539', 'rank' => 61, 'resident_count' => 268, 'activated_blocks' => 384],
    ['name' => '连云港', 'area_code' => '0518', 'rank' => 62, 'resident_count' => 242, 'activated_blocks' => 377],
    ['name' => '威海', 'area_code' => '0631', 'rank' => 63, 'resident_count' => 240, 'activated_blocks' => 376],
    ['name' => '蚌埠', 'area_code' => '0552', 'rank' => 64, 'resident_count' => 254, 'activated_blocks' => 375],
    ['name' => '马鞍山', 'area_code' => '0555', 'rank' => 65, 'resident_count' => 251, 'activated_blocks' => 375],
    ['name' => '西宁', 'area_code' => '0971', 'rank' => 66, 'resident_count' => 264, 'activated_blocks' => 375],
    ['name' => '台州', 'area_code' => '0576', 'rank' => 67, 'resident_count' => 255, 'activated_blocks' => 375],
    ['name' => '汕头', 'area_code' => '0754', 'rank' => 68, 'resident_count' => 219, 'activated_blocks' => 374],
    ['name' => '潍坊', 'area_code' => '0536', 'rank' => 69, 'resident_count' => 234, 'activated_blocks' => 373],
    ['name' => '沧州', 'area_code' => '0317', 'rank' => 70, 'resident_count' => 300, 'activated_blocks' => 366],
    ['name' => '三亚', 'area_code' => '0899', 'rank' => 71, 'resident_count' => 264, 'activated_blocks' => 352],
    ['name' => '南通', 'area_code' => '0513', 'rank' => 72, 'resident_count' => 232, 'activated_blocks' => 350],
    ['name' => '兰州', 'area_code' => '0931', 'rank' => 73, 'resident_count' => 249, 'activated_blocks' => 346],
    ['name' => '扬州', 'area_code' => '0514', 'rank' => 74, 'resident_count' => 229, 'activated_blocks' => 343],
    ['name' => '衢州', 'area_code' => '0570', 'rank' => 75, 'resident_count' => 251, 'activated_blocks' => 334],
    ['name' => '芜湖', 'area_code' => '0553', 'rank' => 76, 'resident_count' => 209, 'activated_blocks' => 332],
    ['name' => '南平', 'area_code' => '0599', 'rank' => 77, 'resident_count' => 203, 'activated_blocks' => 330],
    ['name' => '淄博', 'area_code' => '0533', 'rank' => 78, 'resident_count' => 203, 'activated_blocks' => 324],
    ['name' => '遵义', 'area_code' => '0852', 'rank' => 79, 'resident_count' => 265, 'activated_blocks' => 320],
    ['name' => '鄂尔多斯', 'area_code' => '0477', 'rank' => 80, 'resident_count' => 245, 'activated_blocks' => 320],
    ['name' => '茂名', 'area_code' => '0668', 'rank' => 81, 'resident_count' => 191, 'activated_blocks' => 318],
    ['name' => '景德镇', 'area_code' => '0798', 'rank' => 82, 'resident_count' => 202, 'activated_blocks' => 317],
    ['name' => '呼和浩特', 'area_code' => '0471', 'rank' => 83, 'resident_count' => 215, 'activated_blocks' => 317],
    ['name' => '拉萨', 'area_code' => '0891', 'rank' => 84, 'resident_count' => 239, 'activated_blocks' => 316],
    ['name' => '银川', 'area_code' => '0951', 'rank' => 85, 'resident_count' => 236, 'activated_blocks' => 312],
    ['name' => '泰安', 'area_code' => '0538', 'rank' => 86, 'resident_count' => 196, 'activated_blocks' => 308],
    ['name' => '聊城', 'area_code' => '0635', 'rank' => 87, 'resident_count' => 206, 'activated_blocks' => 303],
    ['name' => '日照', 'area_code' => '0633', 'rank' => 88, 'resident_count' => 177, 'activated_blocks' => 301],
    ['name' => '三明', 'area_code' => '0598', 'rank' => 89, 'resident_count' => 213, 'activated_blocks' => 300],
    ['name' => '韶关', 'area_code' => '0751', 'rank' => 90, 'resident_count' => 185, 'activated_blocks' => 300],
    ['name' => '安庆', 'area_code' => '0556', 'rank' => 91, 'resident_count' => 198, 'activated_blocks' => 299],
    ['name' => '开封', 'area_code' => '0378', 'rank' => 92, 'resident_count' => 182, 'activated_blocks' => 296],
    ['name' => '秦皇岛', 'area_code' => '0335', 'rank' => 93, 'resident_count' => 183, 'activated_blocks' => 288],
    ['name' => '商丘', 'area_code' => '0370', 'rank' => 94, 'resident_count' => 174, 'activated_blocks' => 286],
    ['name' => '菏泽', 'area_code' => '0530', 'rank' => 95, 'resident_count' => 174, 'activated_blocks' => 281],
    ['name' => '黄山', 'area_code' => '0559', 'rank' => 96, 'resident_count' => 175, 'activated_blocks' => 277],
    ['name' => '盐城', 'area_code' => '0515', 'rank' => 97, 'resident_count' => 194, 'activated_blocks' => 276],
    ['name' => '鹤壁', 'area_code' => '0392', 'rank' => 98, 'resident_count' => 176, 'activated_blocks' => 275],
    ['name' => '漳州', 'area_code' => '0596', 'rank' => 99, 'resident_count' => 176, 'activated_blocks' => 274],
    ['name' => '焦作', 'area_code' => '0391', 'rank' => 100, 'resident_count' => 172, 'activated_blocks' => 270],
    ['name' => '廊坊', 'area_code' => '0316', 'rank' => 101, 'resident_count' => 183, 'activated_blocks' => 268],
    ['name' => '岳阳', 'area_code' => '0730', 'rank' => 102, 'resident_count' => 153, 'activated_blocks' => 268],
    ['name' => '莆田', 'area_code' => '0594', 'rank' => 103, 'resident_count' => 160, 'activated_blocks' => 267],
    ['name' => '九江', 'area_code' => '0792', 'rank' => 104, 'resident_count' => 156, 'activated_blocks' => 266],
    ['name' => '宿迁', 'area_code' => '0527', 'rank' => 105, 'resident_count' => 156, 'activated_blocks' => 265],
    ['name' => '南阳', 'area_code' => '0377', 'rank' => 106, 'resident_count' => 175, 'activated_blocks' => 264],
    ['name' => '丽水', 'area_code' => '0578', 'rank' => 107, 'resident_count' => 192, 'activated_blocks' => 264],
    ['name' => '柳州', 'area_code' => '0772', 'rank' => 108, 'resident_count' => 168, 'activated_blocks' => 261],
    ['name' => '龙岩', 'area_code' => '0597', 'rank' => 109, 'resident_count' => 157, 'activated_blocks' => 259],
    ['name' => '淮北', 'area_code' => '0561', 'rank' => 110, 'resident_count' => 159, 'activated_blocks' => 258],
    ['name' => '滨州', 'area_code' => '0543', 'rank' => 111, 'resident_count' => 160, 'activated_blocks' => 258],
    ['name' => '泰州', 'area_code' => '0523', 'rank' => 112, 'resident_count' => 160, 'activated_blocks' => 253],
    ['name' => '江门', 'area_code' => '0750', 'rank' => 113, 'resident_count' => 149, 'activated_blocks' => 251],
    ['name' => '宜昌', 'area_code' => '0717', 'rank' => 114, 'resident_count' => 159, 'activated_blocks' => 249],
    ['name' => '广安', 'area_code' => '0826', 'rank' => 115, 'resident_count' => 154, 'activated_blocks' => 248],
    ['name' => '美团', 'area_code' => 'pgxu', 'rank' => 116, 'resident_count' => 243, 'activated_blocks' => 248],
    ['name' => '安阳', 'area_code' => '0372', 'rank' => 117, 'resident_count' => 156, 'activated_blocks' => 247],
    ['name' => '宜春', 'area_code' => '0795', 'rank' => 118, 'resident_count' => 147, 'activated_blocks' => 247],
    ['name' => '雄安新区', 'area_code' => 'lrvs', 'rank' => 119, 'resident_count' => 214, 'activated_blocks' => 247],
    ['name' => '滁州', 'area_code' => '0550', 'rank' => 120, 'resident_count' => 157, 'activated_blocks' => 246],
    ['name' => '铜陵', 'area_code' => '0562', 'rank' => 121, 'resident_count' => 156, 'activated_blocks' => 243],
    ['name' => '阜阳', 'area_code' => '0558', 'rank' => 122, 'resident_count' => 133, 'activated_blocks' => 240],
    ['name' => '河源', 'area_code' => '0762', 'rank' => 123, 'resident_count' => 147, 'activated_blocks' => 240],
    ['name' => '揭阳', 'area_code' => '0663', 'rank' => 124, 'resident_count' => 141, 'activated_blocks' => 240],
    ['name' => '常德', 'area_code' => '0736', 'rank' => 125, 'resident_count' => 143, 'activated_blocks' => 240],
    ['name' => '德州', 'area_code' => '0534', 'rank' => 126, 'resident_count' => 146, 'activated_blocks' => 237],
    ['name' => '襄阳', 'area_code' => '0710', 'rank' => 127, 'resident_count' => 154, 'activated_blocks' => 236],
    ['name' => '湘潭', 'area_code' => '0732', 'rank' => 128, 'resident_count' => 179, 'activated_blocks' => 235],
    ['name' => '镇江', 'area_code' => '0511', 'rank' => 129, 'resident_count' => 151, 'activated_blocks' => 235],
    ['name' => '梅州', 'area_code' => '0753', 'rank' => 130, 'resident_count' => 159, 'activated_blocks' => 232],
    ['name' => '汕尾', 'area_code' => '0660', 'rank' => 131, 'resident_count' => 128, 'activated_blocks' => 230],
    ['name' => '驻马店', 'area_code' => '0396', 'rank' => 132, 'resident_count' => 141, 'activated_blocks' => 230],
    ['name' => '宠爱哥哥', 'area_code' => 'gdmh', 'rank' => 133, 'resident_count' => 121, 'activated_blocks' => 229],
    ['name' => '池州', 'area_code' => '0566', 'rank' => 134, 'resident_count' => 133, 'activated_blocks' => 227],
    ['name' => '淮南', 'area_code' => '0554', 'rank' => 135, 'resident_count' => 145, 'activated_blocks' => 227],
    ['name' => '清远', 'area_code' => '0763', 'rank' => 136, 'resident_count' => 130, 'activated_blocks' => 225],
    ['name' => '平顶山', 'area_code' => '0375', 'rank' => 137, 'resident_count' => 140, 'activated_blocks' => 225],
    ['name' => '黄冈', 'area_code' => '0713', 'rank' => 138, 'resident_count' => 150, 'activated_blocks' => 225],
    ['name' => '保定', 'area_code' => '0312', 'rank' => 139, 'resident_count' => 143, 'activated_blocks' => 220],
    ['name' => '桂林', 'area_code' => '0773', 'rank' => 140, 'resident_count' => 148, 'activated_blocks' => 216],
    ['name' => '亳州', 'area_code' => '0568', 'rank' => 141, 'resident_count' => 137, 'activated_blocks' => 214],
    ['name' => '承德', 'area_code' => '0314', 'rank' => 142, 'resident_count' => 134, 'activated_blocks' => 214],
    ['name' => '大同', 'area_code' => '0352', 'rank' => 143, 'resident_count' => 128, 'activated_blocks' => 214],
    ['name' => '宝鸡', 'area_code' => '0917', 'rank' => 144, 'resident_count' => 134, 'activated_blocks' => 214],
    ['name' => '新乡', 'area_code' => '0373', 'rank' => 145, 'resident_count' => 125, 'activated_blocks' => 208],
    ['name' => '攀枝花', 'area_code' => '0812', 'rank' => 146, 'resident_count' => 139, 'activated_blocks' => 208],
    ['name' => '邯郸', 'area_code' => '0310', 'rank' => 147, 'resident_count' => 140, 'activated_blocks' => 204],
    ['name' => '营口', 'area_code' => '0417', 'rank' => 148, 'resident_count' => 133, 'activated_blocks' => 204],
    ['name' => '湛江', 'area_code' => '0759', 'rank' => 149, 'resident_count' => 120, 'activated_blocks' => 197],
    ['name' => '宿州', 'area_code' => '0557', 'rank' => 150, 'resident_count' => 115, 'activated_blocks' => 196],
    ['name' => '宣城', 'area_code' => '0563', 'rank' => 151, 'resident_count' => 118, 'activated_blocks' => 194],
    ['name' => '唐山', 'area_code' => '0315', 'rank' => 152, 'resident_count' => 135, 'activated_blocks' => 194],
    ['name' => '漯河', 'area_code' => '0395', 'rank' => 153, 'resident_count' => 122, 'activated_blocks' => 194],
    ['name' => '三门峡', 'area_code' => '0398', 'rank' => 154, 'resident_count' => 118, 'activated_blocks' => 194],
    ['name' => '吉林', 'area_code' => '0432', 'rank' => 155, 'resident_count' => 111, 'activated_blocks' => 194],
    ['name' => '濮阳', 'area_code' => '0393', 'rank' => 156, 'resident_count' => 119, 'activated_blocks' => 193],
    ['name' => '许昌', 'area_code' => '0374', 'rank' => 157, 'resident_count' => 116, 'activated_blocks' => 193],
    ['name' => '遂宁', 'area_code' => '0825', 'rank' => 158, 'resident_count' => 117, 'activated_blocks' => 193],
    ['name' => '宜宾', 'area_code' => '0831', 'rank' => 159, 'resident_count' => 117, 'activated_blocks' => 192],
    ['name' => '衡阳', 'area_code' => '0734', 'rank' => 160, 'resident_count' => 116, 'activated_blocks' => 189],
    ['name' => '北航', 'area_code' => 'iqmr', 'rank' => 161, 'resident_count' => 109, 'activated_blocks' => 189],
    ['name' => '大理', 'area_code' => '0872', 'rank' => 162, 'resident_count' => 134, 'activated_blocks' => 188],
    ['name' => '北海', 'area_code' => '0779', 'rank' => 163, 'resident_count' => 122, 'activated_blocks' => 187],
    ['name' => '信阳', 'area_code' => '0376', 'rank' => 164, 'resident_count' => 119, 'activated_blocks' => 185],
    ['name' => '赣州', 'area_code' => '0797', 'rank' => 165, 'resident_count' => 113, 'activated_blocks' => 185],
    ['name' => '淮安', 'area_code' => '0517', 'rank' => 166, 'resident_count' => 120, 'activated_blocks' => 184],
    ['name' => '六安', 'area_code' => '0564', 'rank' => 167, 'resident_count' => 112, 'activated_blocks' => 182],
    ['name' => '毕节', 'area_code' => '0857', 'rank' => 168, 'resident_count' => 115, 'activated_blocks' => 182],
    ['name' => '阳江', 'area_code' => '0662', 'rank' => 169, 'resident_count' => 108, 'activated_blocks' => 181],
    ['name' => '包头', 'area_code' => '0472', 'rank' => 170, 'resident_count' => 127, 'activated_blocks' => 178],
    ['name' => '抚州', 'area_code' => '0794', 'rank' => 171, 'resident_count' => 104, 'activated_blocks' => 177],
    ['name' => '大兴安岭', 'area_code' => '0457', 'rank' => 172, 'resident_count' => 111, 'activated_blocks' => 174],
    ['name' => '随州', 'area_code' => '0722', 'rank' => 173, 'resident_count' => 96, 'activated_blocks' => 174],
    ['name' => '东营', 'area_code' => '0546', 'rank' => 174, 'resident_count' => 111, 'activated_blocks' => 174],
    ['name' => '莱芜', 'area_code' => '0634', 'rank' => 175, 'resident_count' => 99, 'activated_blocks' => 174],
    ['name' => '咸阳', 'area_code' => '0910', 'rank' => 176, 'resident_count' => 103, 'activated_blocks' => 171],
    ['name' => '广元', 'area_code' => '0839', 'rank' => 177, 'resident_count' => 104, 'activated_blocks' => 170],
    ['name' => '邵阳', 'area_code' => '0739', 'rank' => 178, 'resident_count' => 112, 'activated_blocks' => 169],
    ['name' => '巢湖', 'area_code' => '0565', 'rank' => 179, 'resident_count' => 102, 'activated_blocks' => 167],
    ['name' => '百色', 'area_code' => '0776', 'rank' => 180, 'resident_count' => 111, 'activated_blocks' => 167],
    ['name' => '大庆', 'area_code' => '0459', 'rank' => 181, 'resident_count' => 99, 'activated_blocks' => 166],
    ['name' => '上饶', 'area_code' => '0793', 'rank' => 182, 'resident_count' => 102, 'activated_blocks' => 165],
    ['name' => '贺州', 'area_code' => '0774', 'rank' => 183, 'resident_count' => 100, 'activated_blocks' => 164],
    ['name' => '铜仁', 'area_code' => '0856', 'rank' => 184, 'resident_count' => 105, 'activated_blocks' => 164],
    ['name' => '锦州', 'area_code' => '0416', 'rank' => 185, 'resident_count' => 95, 'activated_blocks' => 164],
    ['name' => '娄底', 'area_code' => '0738', 'rank' => 186, 'resident_count' => 94, 'activated_blocks' => 162],
    ['name' => '永州', 'area_code' => '0746', 'rank' => 187, 'resident_count' => 95, 'activated_blocks' => 160],
    ['name' => '贵港', 'area_code' => '0775', 'rank' => 188, 'resident_count' => 93, 'activated_blocks' => 158],
    ['name' => '伊春', 'area_code' => '0458', 'rank' => 189, 'resident_count' => 91, 'activated_blocks' => 157],
    ['name' => '汉中', 'area_code' => '0916', 'rank' => 190, 'resident_count' => 96, 'activated_blocks' => 157],
    ['name' => '株洲', 'area_code' => '0733', 'rank' => 191, 'resident_count' => 102, 'activated_blocks' => 156],
    ['name' => '佳木斯', 'area_code' => '0454', 'rank' => 192, 'resident_count' => 94, 'activated_blocks' => 154],
    ['name' => '赤峰', 'area_code' => '0476', 'rank' => 193, 'resident_count' => 113, 'activated_blocks' => 154],
    ['name' => '荆州', 'area_code' => '0716', 'rank' => 194, 'resident_count' => 101, 'activated_blocks' => 152],
    ['name' => '齐齐哈尔', 'area_code' => '0452', 'rank' => 195, 'resident_count' => 88, 'activated_blocks' => 151],
    ['name' => '黄石', 'area_code' => '0714', 'rank' => 196, 'resident_count' => 90, 'activated_blocks' => 151],
    ['name' => '咸宁', 'area_code' => '0715', 'rank' => 197, 'resident_count' => 87, 'activated_blocks' => 151],
	['name' => '临汾', 'area_code' => '0357', 'rank' => 198, 'resident_count' => 90, 'activated_blocks' => 151],
	['name' => '延安', 'area_code' => '0911', 'rank' => 199, 'resident_count' => 79, 'activated_blocks' => 151],
	['name' => '铜川', 'area_code' => '0919', 'rank' => 200, 'resident_count' => 93, 'activated_blocks' => 148]
];
    
    $stmt = $pdo->prepare("INSERT INTO cities 
                          (name, area_code, rank, resident_count, activated_blocks, 
                           total_fund, current_balance, popularity, status)
                          VALUES 
                          (:name, :area_code, :rank, :resident_count, :activated_blocks, 0, 0, 0, 'active')");
    
    foreach ($cities as $city) {
        $stmt->execute($city);
    }
    
    return count($cities);
}

// NFT头像初始化
function initNftAvatars($pdo) {
    // 生成所有可能的编号 (AA00-ZZ99)
    $codes = [];
    for ($i = 0; $i < 26; $i++) {
        for ($j = 0; $j < 26; $j++) {
            for ($k = 0; $k < 10; $k++) {
                for ($l = 0; $l < 10; $l++) {
                    $code = chr(65 + $i) . chr(65 + $j) . $k . $l;
                    $codes[] = $code;
                    if (count($codes) >= 2100) break 4;
                }
            }
        }
    }
    
    // 获取所有城市
    $cities = $pdo->query("SELECT id FROM cities")->fetchAll(PDO::FETCH_COLUMN);
    
    // 准备插入语句
    $stmt = $pdo->prepare("INSERT INTO nft_avatars 
                          (code, city, image_url, rarity, created_at)
                          VALUES 
                          (:code, :city, :image_url, :rarity, NOW())");
    
    $rarities = ['common', 'rare', 'epic', 'legendary'];
    $rarityWeights = [70, 20, 8, 2]; // 稀有度分布百分比
    
    $totalInserted = 0;
    
    foreach ($cities as $cityId) {
        $cityName = $pdo->query("SELECT name FROM cities WHERE id = $cityId")->fetchColumn();
        
        foreach ($codes as $index => $code) {
            // 确定稀有度
            $rarity = getWeightedRandom($rarities, $rarityWeights);
            
            // SVG文件路径 (假设文件已存在)
            $imageUrl = "assets/nfts/{$cityName}/{$code}.svg";
            
            $stmt->execute([
                ':code' => $code,
                ':city' => $cityName,
                ':image_url' => $imageUrl,
                ':rarity' => $rarity
            ]);
            
            $totalInserted++;
            
            // 每100条输出一次进度
            if ($totalInserted % 100 === 0) {
                echo "已初始化 {$totalInserted} 个NFT头像...\n";
            }
        }
    }
    
    return $totalInserted;
}

// 加权随机函数
function getWeightedRandom($items, $weights) {
    $totalWeight = array_sum($weights);
    $random = mt_rand(1, $totalWeight);
    $currentWeight = 0;
    
    foreach ($items as $index => $item) {
        $currentWeight += $weights[$index];
        if ($random <= $currentWeight) {
            return $item;
        }
    }
    
    return $items[0];
}

// 执行初始化
try {
    $pdo->beginTransaction();
    
    echo "开始初始化城市数据...\n";
    $cityCount = initCities($pdo);
    echo "成功初始化 {$cityCount} 个城市\n";
    
    //echo "开始初始化NFT头像...\n";
    //$nftCount = initNftAvatars($pdo);
    //echo "成功初始化 {$nftCount} 个NFT头像\n";
    
    $pdo->commit();
    echo "初始化完成！\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "初始化失败: " . $e->getMessage() . "\n";
}
?>

