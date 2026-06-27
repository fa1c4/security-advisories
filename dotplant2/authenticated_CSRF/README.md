# dotplant2_delete_slide_missing_verbfilter

Standalone Docker PoC for `DevGroup-ru/dotplant2` finding `CAND-78787ea6a618`.

## Bug

`SliderController::actionDeleteSlide($id)` deletes a slide and can be dispatched via GET.

## Root cause

`SliderController::behaviors()` contains access control but lacks a Yii `VerbFilter` restricting `delete-slide` to POST/DELETE. Yii CSRF validation does not protect GET requests.

## Run

```bash
docker build -t poc-dotplant2-delete-slide .
docker run --rm poc-dotplant2-delete-slide
```

This PoC is local-only and uses in-memory state. It does not start dotplant2, touch a database, or contact external services.
