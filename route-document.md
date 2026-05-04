# Material Stock Management API Routing Documentation

This document describes the API endpoints and logic flow for managing Material Stock via `MaterialTransaction` and `WarehouseActivity`. The implementation standardizes the material stock logic to be similar to the unit transaction and unit warehouse activity flow.

## 1. Material Transaction State Management

Updates the overall transaction status (e.g., `draft`, `inbound_incoming_goods`, `outbound_delivered`) and applies the `is_forecast` rule on the material items based on the transaction state.

**Endpoint:**
`PUT /api/transaction/material-transaction/{id}/update-state`

**Request Body:**
```json
{
    "stock_state": "inbound_incoming_goods",
    "material_transaction_details": [
        1, 2, 3
    ]
}
```
- `stock_state`: The destination state. Allowed values for purchase: `['draft', 'cancel', 'rejected', 'prepare', 'inbound_purchase_order', 'inbound_incoming_goods', 'inbound_receipt']`. Allowed values for sales: `['draft', 'cancel', 'prepare', 'outbound_reserved', 'outbound_in_transit', 'outbound_delivered']`.
- `material_transaction_details`: (Optional) Array of Material Transaction Detail IDs to be updated. If omitted, all detail IDs belonging to the transaction are updated.

---

## 2. Warehouse Activity For Material Transactions

Stock movement for material transactions relies on `WarehouseActivityController`, which processes an array of material transaction details using the warehouse movement log (`WarehouseMovement`).

### 2.1. Receipt Material Stock
Approves inbound stock (purchased items) into a warehouse and finalizes the `in_stock` value.

**Endpoint:**
`PUT /api/warehouse/warehouse-activity/{id}/receipt-material-stock`

**Request Body:**
```json
{
    "material_transaction_details": [
        1, 2, 3
    ]
}
```

### 2.2. Dispatch Material Stock
Releases outbound stock (sold items) out of a warehouse.

**Endpoint:**
`PUT /api/warehouse/warehouse-activity/{id}/dispatch-material-stock`

**Request Body:**
```json
{
    "material_transaction_details": [
        1, 2, 3
    ]
}
```

### 2.3. Return Material Stock
Reverts a purchased stock receipt from the warehouse.

**Endpoint:**
`POST /api/warehouse/warehouse-activity/return-material-stock`

**Request Body:**
```json
{
    "material_transaction_details": [
        1, 2, 3
    ]
}
```

### 2.4. Refund Material Stock
Reverts a sold stock dispatch back into the warehouse.

**Endpoint:**
`POST /api/warehouse/warehouse-activity/refund-material-stock`

**Request Body:**
```json
{
    "material_transaction_details": [
        1, 2, 3
    ]
}
```

---

## 3. Material Master Data

Provides detailed information about materials, including global stock and warehouse-specific stock levels.

### 3.1. Get Material Detail
Retrieves specific material data with calculated stock levels.

**Endpoint:**
`GET /api/master-data/material/{id}`

**Query Parameters:**
- `company_id`: (Optional) If provided, returns `available_stock_warehouse` and `forecasted_stock_warehouse` for the warehouse associated with the company, along with a paginated list of `transaction_details`.

**Response Highlights:**
- `stock`: Current finalized stock (Global).
- `forecast_stock`: Projected stock including pending transactions (Global).
- `available_stock_warehouse`: Finalized stock for the requested warehouse.
- `forecasted_stock_warehouse`: Projected stock for the requested warehouse.
- `transaction_details`: List of related transaction records (if `company_id` is provided).

---

## Logic Summary
- **Transactions & Details:** `MaterialTransaction` represents a grouped transaction document; `MaterialTransactionDetail` specifies the quantities & type of the items inside that transaction.
- **State Preparation:** The `updateState` endpoint on `MaterialTransactionController` aligns the items with standard inventory transition codes (e.g. `inbound_incoming_goods`). It also toggles `is_forecast`.
- **Validation:** Standardized constraints apply for receipt or dispatch execution:
  - The transaction state must allow for stock movement.
  - Financial verification ensures the transaction `is_paid`.
  - Specific target details must map to the supplier/customer defined in `WarehouseActivity`.
- **Execution:** Once verified, calling `receiptMaterialStock` / `dispatchMaterialStock` triggers `receiptStock()` / `dispatchStock()` on the model, injecting new logs into `WarehouseMovement` and toggling `in_stock` on the respective material details.
