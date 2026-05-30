<?php
class CityBCT {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // 获取城市人气值信息
    public function getCityBCT($city) {
        $stmt = $this->pdo->prepare("SELECT * FROM city_bct WHERE city = ?");
        $stmt->execute([$city]);
        return $stmt->fetch();
    }
    
    // 更新城市人气值价格
    public function updatePrice($city, $newPrice) {
        $stmt = $this->pdo->prepare("UPDATE city_bct SET current_price = ?, last_updated = NOW() WHERE city = ?");
        return $stmt->execute([$newPrice, $city]);
    }
    
    // 获取所有城市人气值信息
    public function getAllCitiesBCT() {
        $stmt = $this->pdo->prepare("SELECT * FROM city_bct ORDER BY city");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // 根据供需自动调整价格
    public function autoAdjustPrice($city) {
        // 获取当前供需数据
        $buyOrders = $this->getPendingOrdersCount($city, 'buy');
        $sellOrders = $this->getPendingOrdersCount($city, 'sell');
        
        // 简单供需算法
        $ratio = $buyOrders / max(1, $sellOrders);
        $cityInfo = $this->getCityBCT($city);
        
        if($ratio > 1.2) {
            // 需求旺盛，价格上涨5%
            $newPrice = $cityInfo['current_price'] * 1.05;
        } elseif($ratio < 0.8) {
            // 供给过剩，价格下跌5%
            $newPrice = $cityInfo['current_price'] * 0.95;
        } else {
            // 供需平衡，价格不变
            $newPrice = $cityInfo['current_price'];
        }
        
        // 确保不低于基础价格
        $newPrice = max($newPrice, $cityInfo['base_price']);
        
        $this->updatePrice($city, $newPrice);
        return $newPrice;
    }
    
    private function getPendingOrdersCount($city, $type) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bct_orders WHERE city = ? AND type = ? AND status = 'pending'");
        $stmt->execute([$city, $type]);
        return $stmt->fetchColumn();
    }
}
?>