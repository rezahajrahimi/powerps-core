{{-- <form method="post" action="{{url('shop')}}">
    {{csrf_field()}}
    <input type="text" name="price">
    <button type="submit">تکمیل خرید {{$price}} / {{$invoiceID}}</button>
</form> --}}

<div class="wrapper ">
    <form method="post" action="{{ url('shop') }}" >
        {{ csrf_field() }}
        <input type="text"  value="{{ $account_id }}" name="account_id" class="hidden">
        <input type="text"  value="{{ $invoiceID }}" name="invoiceID" class="hidden">
        <div class="card px-4">
            <div class=" my-3 label-header">
                <p class="h8">عملیات پرداخت</p>
            </div>

            <div class="debit-card mb-3 container">
                <div class="d-flex flex-column h-100">
                    <label class="d-block">
                        <div class="d-flex position-relative">
                            <div>
                                <img src="https://www.freepnglogos.com/uploads/visa-inc-logo-png-11.png"
                                    class="img-visa visa" alt="">
                                <p class="mt-2 mb-4 text-white fw-bold">شماره فاکتور: {{ $invoiceID }}</p>
                            </div>
                        </div>
                    </label>
                    <div class="mt-auto fw-bold d-flex align-items-center justify-content-between">
                        <p>مبلغ: {{ $price }} تومان</p>
                    </div>
                </div>
            </div>

            <div class="btn mb-4">

                <button class="btn-submit" type="submit" >پرداخت </button>

            </div>
        </div>
    </form>

</div>

<style>
    @import url('https://fonts.googleapis.com/css?family=Montserrat:400,700,500&display=swap');

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Montserrat', sans-serif;
    }

    body {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: azure;
        padding: 10px 20px;
    }

    .wrapper {
        min-width: 60vw;
        max-width: 600px;
        box-shadow: 3px 3px 5px #1b1b1ba2;

    }

    .container {
        min-width: 60vw;
    }
    .hidden {
        visibility: hidden;
    }

    .label-header {
        direction: rtl;
        padding-right: 2rem;
    }

    .btn-submit {
        padding: 0.3rem 2.5rem;
        font-size: 1.5rem;
        border-radius: 0.7rem;
        background: #0093E9;
        font-weight: 900;
        transition: 0.3s all ease-in-out;
    }

    .btn-submit:hover {
        background: white;
        color: #0093E9;
    }

    .card {
        background-color: #413f3f;
    }

    p {
        margin: 0px;
    }

    .h8 {
        font-size: 25px;
        font-weight: 600;
        color: white;
    }

    .card .atm {
        width: 90px;
        height: 90px;
        object-fit: cover;
    }

    .card .visa {
        width: 50px;
        height: 20px;
        object-fit: fill;
    }


    .card .master {
        width: 50px;
        height: 50px;
        object-fit: fill;
    }

    .debit-card {
        width: 100%;
        height: 180px;
        padding: 20px;
        background-color: #0093E9;
        background-image: linear-gradient(160deg, #0093E9 0%, #80D0C7 100%);
        position: relative;
        border-radius: 5px;
        box-shadow: 3px 3px 5px #0000001a;
        transition: all 0.3s ease-in;
        cursor: pointer;
    }

    .debit-card:hover {
        box-shadow: 5px 3px 5px #000000a2;
    }

    .card-2 {
        background-color: #21D4FD;
        background-image: linear-gradient(116deg, #21D4FD 0%, #B721FF 100%);
    }

    .text-muted {
        font-size: 0.8rem;
    }

    .text-white {
        font-size: 14px;
    }

    .input {
        position: absolute;
        top: 6px;
        right: 0;
    }

    input[type="radio"] {
        appearance: none;
        width: 20px;
        height: 20px;
        background-color: #eee;
        position: relative;
        border-radius: 3px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        outline: none;
    }

    input[type="radio"]:after {
        position: absolute;
        width: 100%;
        height: 100%;
        font-family: " Font Awesome 5 Free"; font-weight: 600; content: "\f00c" ; color: #fff; font-size: 15px;
        display: none; } input[type="radio"]:checked, input[type="radio"]:checked:hover { background-color: blue; }
        input[type="radio"]:checked::after { display: flex; align-items: center; justify-content: center; }
        input[type="radio"]:hover { background-color: #ddd; } .btn { width: 100%; height: 50px; border: 1px solid
        #0093E9; display: flex; justify-content: center; align-items: center; color: #0093E9; transition: all 0.5s ease;
        font-weight: 500; } /* .btn:hover { color: #fff; background-color: #0093E9; } */ </style>
