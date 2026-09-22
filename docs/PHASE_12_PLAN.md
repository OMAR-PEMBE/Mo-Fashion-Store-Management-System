# Phase 12 — Returns implementation plan

Status: implemented and verified. See [the Phase 12 report](PHASE_12.md).

## Approved decisions

The owner resolved the return policies before implementation:

- Maximum three days, implemented as 72 elapsed hours from completed sale,
  inclusive, checked at creation, approval and completion.
- Customer receipt or matching sale record is sufficient proof.
- Administrators and salespeople may both approve within their existing sale
  access scope. Return-specific permissions are now assigned to both roles.

All implemented returns must reference an original sale and its actual sale
items, as required by the more detailed database, API and workflow specifications.
Permitting a customer without a paper receipt does not imply accepting a return
that cannot be matched to a recorded sale.

## Confirmed behavior

- Create, approve, complete and reject through ReturnService. Preserve the
  documented PENDING, APPROVED, COMPLETED, REJECTED and CANCELLED status values;
  expose only the transitions required by the approved workflow.
- Validate quantities against the original sale and completed returns. Recheck
  under locks at completion so simultaneous requests cannot over-return stock.
- Only SELLABLE items restore physical inventory through a RETURN movement.
  DAMAGED, DEFECTIVE and OTHER items remain in return history without increasing
  sellable stock. Reservations must remain unchanged.
- Calculate the historical returned cost from original sale_item.unit_cost and
  returned quantity using decimal arithmetic. Preserve original sale snapshots.
- A merchandise return does not itself issue a refund, rewrite original sale
  revenue, or mark the sale REFUNDED. Refund processing belongs to Phase 13.
- Make stock changes, return state, cost adjustment and audit recording one
  transaction. Reject or safely replay duplicate requests without repeat effects.

## Implementation sequence

1. Record the approved policies in the source requirements and a server-side
   policy configuration with explicit eligibility validation.
2. Add returns/return_items, foreign keys to sales and sale_items, numbering and
   retry protection. Retain actor and transition timestamps for review history.
   Use a PHP model name such as SaleReturn because return is a language keyword.
3. Implement ReturnService and authorization, checking that each selected item
   belongs to the specified eligible sale. Never accept client-submitted costs,
   stock-restored flags, actor IDs or completion statuses as authoritative.
4. Integrate stock restoration with InventoryService. Its current generic
   stock-in path requires inventory.adjust; reconcile this with the approved
   return permission without granting staff general adjustment access.
5. Follow existing product → variant → inventory lock ordering. Serialize
   competing returns against the same original sale before validating remaining
   quantities; lock multi-line variants in stable ID order.
6. Add sale lookup, item/remaining-quantity selection, condition/reason entry,
   review, approval/rejection and completion screens. Link return history to the
   original sale and enforce authorization independently of visible buttons.
7. Verify automated behavior and desktop/mobile usability, then apply the local
   migration and document the implemented behavior and remaining limitations.

## Verification matrix

| Area | Required checks |
| --- | --- |
| Eligibility | Approved date boundary; required proof; missing/invalid original sale; wrong-sale item |
| Quantities | Positive integers; partial return; previous returns; excess total; duplicate lines |
| Stock | Sellable restoration once; damaged/defective/other do not restore; reservations unchanged |
| History | Original sale unchanged; original cost used after a later purchase changes WAC |
| Transitions | Valid approval/completion/rejection; rejected or completed return cannot be processed again |
| Authorization | Roles, revoked permissions, other staff's records, hidden cost data, CSRF |
| Atomicity | Multi-line failure and audit failure restore all pre-operation state |
| MySQL races | Two returns competing for remaining quantity; duplicate completion; return versus stock sale/purchase |
| Browser | Guided workflow and history at desktop and mobile widths, errors and confirmation dialogs |

The original preparation was documentation-only. Implementation and verification
results are now recorded in PHASE_12.md.
