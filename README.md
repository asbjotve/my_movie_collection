# my_movie_collection
An online database of owned movies, with possibility to fill data from The TVDB or The Movie Database (TMDb).

## To-do
- [ ] Function: Add movie
- [ ] Function: Bulk-add movies
- [ ] Function: Addd list of movies, based on a .csv
- [ ] Function: Remove a movie from collection

## Purpose and background
When I first started organizing my collection, I used a simple Excel worksheet. Later, I transitioned to 
Invelos DVD Profiler. While there's nothing inherently wrong with Invelos DVD Profiler, certain 
limitations prompted me to start my own collection project. Until now, I've been experimenting with FileMaker Pro. 
FileMaker Pro is a powerful tool, but it's difficult to share with others, and tracking my 
ongoing scripts and database edits proved challenging.

This brings us to the current project. With the advent of AI (Artificial Intelligence), developing such a 
project has become more accessible. Since I'm not fluent in Python, for example, I decided to start 
from scratch—beginning with a complete redesign of the database ER-diagram. This project will 
include a website where you can add and remove movies, as well as a FastAPI application that will 
serve as the connection between the webpage and the database.

## Requirements
- MariaDB / MySQL
- Python
- FastAPI

## Regarding MariaDB / MySQL
......
