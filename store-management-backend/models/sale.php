<?php
class Sale {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    # CREATE
    public function create($userId, $customerId, $paymentMethod, $status, $items, $discount = 0, $tax = 0,$bankAccountId=1) {
        try {
            $this->pdo->beginTransaction();

            // ✅ Validate payment method
            $validMethods = ['cash','bank_transfer'];
            if (!in_array($paymentMethod, $validMethods)) {
                throw new Exception("Invalid payment method");
            }

            // ✅ Validate status
            $validStatuses = ['completed','refunded'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception("Invalid sale status");
            }

            $totalAmount = 0;
            $stmtProduct = $this->pdo->prepare("SELECT id, price, cost, stock FROM products WHERE id=?");

            $saleItems = [];
            foreach ($items as $item) {
                $stmtProduct->execute([$item['product_id']]);
                $product = $stmtProduct->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new Exception("Product ID {$item['product_id']} not found");
                }

                $quantity = $item['quantity'];
                $price = $product['price'];
                $cost = $product['cost'];
                $stock = $product['stock']; // Get the current stock

                // ✅ Check if there's enough stock
                if ($quantity > $stock) {
                    throw new Exception("Not enough stock for Product ID {$item['product_id']}. Available: $stock, Requested: $quantity");
                }

                $lineTotal = $price * $quantity;
                $totalAmount += $lineTotal;

                $saleItems[] = [
                    "product_id" => $product['id'],
                    "quantity" => $quantity,
                    "price" => $price,
                    "cost" => $cost,
                    "discount_amount" => 0,
                    "total" => $lineTotal
                ];
            }

            $finalAmount = ($totalAmount - $discount) + $tax;

            // ✅ Insert into sales (using CURDATE for DATE field)
            $stmtSale = $this->pdo->prepare("INSERT INTO sales 
                (customer_id, sale_date, total_amount, discount_amount, tax_amount, final_amount, payment_method, status, user_id, created_at) 
                VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, NOW())");

            $stmtSale->execute([
                $customerId,
                $totalAmount,
                $discount,
                $tax,
                $finalAmount,
                $paymentMethod,
                $status,
                $userId
            ]);

            $saleId = $this->pdo->lastInsertId();

            // Insert sale items + reduce stock + log inventory transactions
            $stmtItem = $this->pdo->prepare("INSERT INTO sale_items 
                (sale_id, product_id, quantity, price, cost, discount_amount, total) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmtInventory = $this->pdo->prepare("INSERT INTO inventory_transactions
                (product_id, transaction_type, quantity, reason, related_sale_id, user_id, created_at)
                VALUES (?, 'OUT', ?, 'Sale', ?, ?, NOW())");

            foreach ($saleItems as $si) {
                $stmtItem->execute([
                    $saleId,
                    $si['product_id'],
                    $si['quantity'],
                    $si['price'],
                    $si['cost'],
                    $si['discount_amount'],
                    $si['total']
                ]);

                // Reduce stock
                $this->pdo->prepare("UPDATE products SET stock = stock - ? WHERE id=?")
                          ->execute([$si['quantity'], $si['product_id']]);

                // Log inventory transaction
                $stmtInventory->execute([
                    $si['product_id'],
                    $si['quantity'],
                    $saleId,
                    $userId
                ]);
            }

            // ✅ Optional: auto-insert customer payment
            $stmtPayment = $this->pdo->prepare("INSERT INTO customer_payments 
                (customer_id, sale_id, amount, payment_date, payment_method) 
                VALUES (?, ?, ?, CURDATE(), ?)");
            $stmtPayment->execute([$customerId, $saleId, $finalAmount, $paymentMethod]);

            // ✅ Auto-create bank transaction if payment is via bank_transfer
            if ($paymentMethod === 'bank_transfer') {
                // either from request or fallback to default account (id=1 here for example)
                $bankAccountId = $items['bank_account_id'] ?? 1; 

                $stmtBank = $this->pdo->prepare("INSERT INTO bank_transactions
                    (bank_account_id, transaction_type, amount, description, transaction_date, related_sale_id, created_at)
                    VALUES (?, 'INCOME', ?, ?, CURDATE(), ?, NOW())");

                $stmtBank->execute([
                    $bankAccountId,
                    $finalAmount,
                    "Sale #$saleId payment",
                    $saleId
                ]);
            }

            $this->pdo->commit();
            return $saleId;


        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    # READ ALL with search + pagination
    public function getAll($search = "", $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $params = [];

        $sql = "SELECT s.*, u.name AS user_name, c.name AS customer_name
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN customers c ON s.customer_id = c.id";

        if ($search) {
            $sql .= " WHERE c.name LIKE ? OR s.payment_method LIKE ? OR s.status LIKE ?";
            $params = ["%$search%", "%$search%", "%$search%"];
        }

        $sql .= " ORDER BY s.id DESC LIMIT $limit OFFSET $offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count for pagination
        $countSql = "SELECT COUNT(*) FROM sales s LEFT JOIN customers c ON s.customer_id=c.id";
        if ($search) {
            $countSql .= " WHERE c.name LIKE ? OR s.payment_method LIKE ? OR s.status LIKE ?";
        }
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = $stmtCount->fetchColumn();

        return [
            "data" => $rows,
            "page" => $page,
            "limit" => $limit,
            "total" => $total,
            "pages" => ceil($total / $limit)
        ];
    }

    # READ by ID
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT s.*, u.name AS user_name, c.name AS customer_name
                                     FROM sales s
                                     LEFT JOIN users u ON s.user_id = u.id
                                     LEFT JOIN customers c ON s.customer_id = c.id
                                     WHERE s.id=?");
        $stmt->execute([$id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sale) {
            $stmtItems = $this->pdo->prepare("SELECT si.*, p.name AS product_name
                                              FROM sale_items si
                                              LEFT JOIN products p ON si.product_id = p.id
                                              WHERE si.sale_id=?");
            $stmtItems->execute([$id]);
            $sale['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        }

        return $sale;
    }

    # UPDATE
    public function update($id, $paymentMethod, $status) {
        $validMethods = ['cash','card','bank_transfer','mobile'];
        $validStatuses = ['completed','refunded'];

        if ($paymentMethod && !in_array($paymentMethod, $validMethods)) {
            throw new Exception("Invalid payment method");
        }
        if ($status && !in_array($status, $validStatuses)) {
            throw new Exception("Invalid sale status");
        }

        $stmt = $this->pdo->prepare("UPDATE sales SET payment_method=?, status=? WHERE id=?");
        return $stmt->execute([$paymentMethod, $status, $id]);
    }

    # DELETE
    public function delete($id) {
        try {
            $this->pdo->beginTransaction();

            $stmtItems = $this->pdo->prepare("SELECT product_id, quantity FROM sale_items WHERE sale_id=?");
            $stmtItems->execute([$id]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                // Restore stock
                $this->pdo->prepare("UPDATE products SET stock = stock + ? WHERE id=?")
                          ->execute([$item['quantity'], $item['product_id']]);

                // Log inventory reversal
                $this->pdo->prepare("INSERT INTO inventory_transactions
                    (product_id, transaction_type, quantity, reason, related_sale_id, user_id, created_at)
                    VALUES (?, 'IN', ?, 'Sale Deleted', ?, ?, NOW())")
                          ->execute([$item['product_id'], $item['quantity'], $id, 1]); // TODO: replace 1 with current user
            }

            $this->pdo->prepare("DELETE FROM sales WHERE id=?")->execute([$id]);

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
?>