<?php

class Cart {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 添加商品到购物车
     */
    /* public function addItem($userId, $productId, $quantity = 1) {
        try {
            // 检查商品是否存在且可用
            $product = $this->getProductInfo($productId);
            if (!$product) {
                throw new Exception("商品不存在");
            }
            
            if ($product['status'] != 'active') {
                throw new Exception("商品已下架");
            }
            
            if ($product['stock'] < $quantity) {
                throw new Exception("商品库存不足");
            }
            
            // 检查购物车中是否已有该商品
            $existingItem = $this->getCartItem($userId, $productId);
            
            if ($existingItem) {
                // 更新数量
                $newQuantity = $existingItem['quantity'] + $quantity;
                return $this->updateQuantity($existingItem['id'], $newQuantity, $userId);
            } else {
                // 添加新商品
                $sql = "INSERT INTO cart_items (user_id, product_id, quantity, created_at) 
                        VALUES (?, ?, ?, NOW())";
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([$userId, $productId, $quantity]);
            }
        } catch (Exception $e) {
            throw new Exception("添加商品到购物车失败: " . $e->getMessage());
        }
    } */
    
    /**
     * 获取购物车商品列表
     */
    /* public function getCartItems($userId) {
        try {
            $sql = "SELECT ci.id, ci.product_id, ci.quantity, 
                           p.name, p.price, p.image_url, p.stock, p.status,
                           s.shop_name, s.user_id as shop_owner_id
                    FROM cart_items ci
                    INNER JOIN products p ON ci.product_id = p.id
                    LEFT JOIN shops s ON p.shop_id = s.id
                    WHERE ci.user_id = ? AND ci.deleted_at IS NULL
                    ORDER BY ci.created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 检查商品状态，标记缺货但不自动移除
            $validItems = [];
            foreach ($items as $item) {
                if ($item['status'] == 'active') {
                    $item['out_of_stock'] = ($item['stock'] <= 0);
                    $validItems[] = $item;
                } else {
                    // 仅移除已下架的商品
                    $this->removeItem($item['id'], $userId);
                }
            }
            
            return $validItems;
        } catch (Exception $e) {
            error_log("获取购物车商品失败: " . $e->getMessage());
            return [];
        }
    } */
    
    /**
     * 更新购物车商品数量
     */
    public function updateQuantity($cartItemId, $quantity, $userId) {
        try {
            // 验证购物车项属于该用户
            $cartItem = $this->getCartItemById($cartItemId);
            if (!$cartItem || $cartItem['user_id'] != $userId) {
                throw new Exception("购物车项不存在");
            }
            
            // 检查商品库存
            $product = $this->getProductInfo($cartItem['product_id']);
            if ($product['stock'] < $quantity) {
                throw new Exception("商品库存不足，最大可购买数量: " . $product['stock']);
            }
            
            if ($quantity <= 0) {
                return $this->removeItem($cartItemId, $userId);
            }
            
            $sql = "UPDATE cart_items SET quantity = ?, updated_at = NOW() 
                    WHERE id = ? AND user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$quantity, $cartItemId, $userId]);
        } catch (Exception $e) {
            throw new Exception("更新商品数量失败: " . $e->getMessage());
        }
    }
    
    /**
     * 从购物车移除商品
     */
    public function removeItem($cartItemId, $userId) {
        try {
            $sql = "UPDATE cart_items SET deleted_at = NOW() 
                    WHERE id = ? AND user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$cartItemId, $userId]);
        } catch (Exception $e) {
            throw new Exception("移除商品失败: " . $e->getMessage());
        }
    }
    
    /**
     * 清空购物车
     */
    public function clearCart($userId) {
        try {
            $sql = "UPDATE cart_items SET deleted_at = NOW() 
                    WHERE user_id = ? AND deleted_at IS NULL";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$userId]);
        } catch (Exception $e) {
            throw new Exception("清空购物车失败: " . $e->getMessage());
        }
    }
    
    /**
     * 获取购物车商品数量
     */
    /* public function getItemCount($userId) {
        try {
            $sql = "SELECT SUM(quantity) as total_count 
                    FROM cart_items 
                    WHERE user_id = ? AND deleted_at IS NULL";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['total_count'] ?: 0;
        } catch (Exception $e) {
            error_log("获取购物车商品数量失败: " . $e->getMessage());
            return 0;
        }
    } */
    
    /**
     * 获取购物车总金额
     */
    public function getTotalAmount($userId) {
        try {
            $sql = "SELECT SUM(ci.quantity * p.price_bct) as total_amount
                    FROM cart_items ci
                    INNER JOIN products p ON ci.product_id = p.id
                    WHERE ci.user_id = ? AND ci.deleted_at IS NULL 
                    AND p.status = 'active' AND p.stock > 0";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['total_amount'] ?: 0;
        } catch (Exception $e) {
            error_log("获取购物车总金额失败: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 检查购物车中是否包含某个商品
     */
    public function hasProduct($userId, $productId) {
        try {
            $sql = "SELECT COUNT(*) as count 
                    FROM cart_items 
                    WHERE user_id = ? AND product_id = ? AND deleted_at IS NULL";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId, $productId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("检查购物车商品失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取单个购物车项
     */
    private function getCartItem($userId, $productId) {
        try {
            $sql = "SELECT * FROM cart_items 
                    WHERE user_id = ? AND product_id = ? AND deleted_at IS NULL";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId, $productId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * 根据ID获取购物车项
     */
    private function getCartItemById($cartItemId) {
        try {
            $sql = "SELECT * FROM cart_items WHERE id = ? AND deleted_at IS NULL";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$cartItemId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * 获取商品信息
     */
    private function getProductInfo($productId) {
        try {
            $sql = "SELECT * FROM products WHERE id = ? AND deleted_at IS NULL";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$productId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * 验证购物车商品有效性（在结算前调用）
     */
    public function validateCartItems($userId) {
        try {
            $cartItems = $this->getCartItems($userId);
            $validItems = [];
            $invalidItems = [];
            
            foreach ($cartItems as $item) {
                $product = $this->getProductInfo($item['product_id']);
                
                if (!$product || $product['status'] != 'active') {
                    $invalidItems[] = [
                        'item' => $item,
                        'reason' => '商品已下架'
                    ];
                    continue;
                }
                
                if ($product['stock'] < $item['quantity']) {
                    $invalidItems[] = [
                        'item' => $item,
                        'reason' => '库存不足，当前库存: ' . $product['stock']
                    ];
                    continue;
                }
                
                $validItems[] = $item;
            }
            
            return [
                'valid_items' => $validItems,
                'invalid_items' => $invalidItems
            ];
        } catch (Exception $e) {
            throw new Exception("验证购物车商品失败: " . $e->getMessage());
        }
    }
    
    /**
     * 获取购物车统计信息
     */
    public function getCartStats($userId) {
        try {
            $itemCount = $this->getItemCount($userId);
            $totalAmount = $this->getTotalAmount($userId);
            $cartItems = $this->getCartItems($userId);
            
            return [
                'item_count' => $itemCount,
                'total_amount' => $totalAmount,
                'product_count' => count($cartItems),
                'items' => $cartItems
            ];
        } catch (Exception $e) {
            error_log("获取购物车统计信息失败: " . $e->getMessage());
            return [
                'item_count' => 0,
                'total_amount' => 0,
                'product_count' => 0,
                'items' => []
            ];
        }
    }
	
	/**
     * 添加商品到购物车
     */
    public function addItem($userId, $productId, $quantity = 1) {
        try {
            // 检查商品是否存在且可用
            $product = $this->getProductInfo($productId);
            if (!$product) {
                throw new Exception("商品不存在");
            }
            
            if ($product['status'] != 'active') {
                throw new Exception("商品已下架");
            }
            
            if ($product['stock'] < $quantity) {
                throw new Exception("商品库存不足");
            }
            
            // 检查购物车中是否已有该商品
            $existingItem = $this->getCartItem($userId, $productId);
            
            if ($existingItem) {
                // 更新数量
                $newQuantity = $existingItem['quantity'] + $quantity;
                if ($newQuantity > $product['stock']) {
                    throw new Exception("商品库存不足，最多可购买 " . $product['stock'] . " 件");
                }
                return $this->updateQuantity($existingItem['id'], $newQuantity, $userId);
            } else {
                // 添加新商品
                $sql = "INSERT INTO cart_items (user_id, product_id, quantity, created_at) 
                        VALUES (?, ?, ?, NOW())";
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([$userId, $productId, $quantity]);
            }
        } catch (Exception $e) {
            throw new Exception("添加商品到购物车失败: " . $e->getMessage());
        }
    }
    
    /**
     * 获取购物车商品列表
     */
    public function getCartItems($userId) {
        try {
            $sql = "SELECT ci.id, ci.product_id, ci.quantity, 
                           p.name, p.price_bct as price, p.main_image as image_url, p.stock, p.status,
                           s.id as shop_id, s.shop_name, s.user_id as shop_owner_id
                    FROM cart_items ci
                    INNER JOIN products p ON ci.product_id = p.id
                    LEFT JOIN shops s ON p.shop_id = s.id
                    WHERE ci.user_id = ? AND ci.deleted_at IS NULL
                    ORDER BY s.id, ci.created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 检查商品状态，标记缺货但不自动移除
            $validItems = [];
            foreach ($items as $item) {
                if ($item['status'] == 'active') {
                    $item['out_of_stock'] = ($item['stock'] <= 0);
                    $validItems[] = $item;
                } else {
                    // 仅移除已下架的商品
                    $this->removeItem($item['id'], $userId);
                }
            }
            
            return $validItems;
        } catch (Exception $e) {
            error_log("获取购物车商品失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取购物车商品数量
     */
    public function getItemCount($userId) {
        try {
            $sql = "SELECT SUM(quantity) as total_count 
                    FROM cart_items 
                    WHERE user_id = ? AND deleted_at IS NULL";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['total_count'] ?: 0;
        } catch (Exception $e) {
            error_log("获取购物车商品数量失败: " . $e->getMessage());
            return 0;
        }
    }
}
?> 