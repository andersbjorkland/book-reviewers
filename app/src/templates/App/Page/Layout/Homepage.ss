<section class="container">
    <h1>The Home of Book Reviewers</h1>
    <div class="Actions line">
        $SearchBookForm
    </div>

    <!-- This div was as the layout originally was -->
    <div class="line">
        <br>
        <h2>Latest Reviews</h2>
        <% loop $LatestReviews %>
            <div>
                <h3>$Book.Title</h3>
                <p>
                    <b>$Title</b> <br>
                    $Review.FirstParagraph <br>
                    $Up.RatingStars($Rating)
                </p>
            </div>
        <% end_loop %>
    </div>
</section>
