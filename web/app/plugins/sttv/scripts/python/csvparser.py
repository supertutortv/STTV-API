import csv
import json
import argparse

RUBRIC_FILE = 'sttv_rubrics.py'

# Sets up the format and messages for the cli
parser = argparse.ArgumentParser(description='Accepts a .csv file and outputs a dictionary for practice test submissions')
parser.add_argument('file', metavar='path', type=str, nargs=1,
                    help='filepath of .csv to parse')

# Parse those args
args = parser.parse_args()

# Template for the rubric
rubric = {
    'data' : {},
    'grading' : {},
    'analysis' : {
        'blank' : {},
        'score' : {},
    },
    'buckets' : {}
}

# Get the file to parse
filePath = args.file[0]

with open(filePath, 'r') as csvfile:
    try:
        # Create a generator with lines from the csvfile
        reader = csv.reader(csvfile)

        # Put those lines where they belong in the object
        rubric['data']['name'] = next(reader)[1]
        rubric['data']['link'] = next(reader)[1]
        next(reader)

        rubric['analysis']['blank']['threshold'] = next(reader)[1]
        rubric['analysis']['blank']['link'] = next(reader)[1]
        next(reader)

        rubric['analysis']['score']['threshold'] = next(reader)[1]
        rubric['analysis']['score']['link'] = next(reader)[1]

        scoreRow = next(reader)

        # Make all of the scaled:raw rows into
        # key-value pairs in the buckets object
        try:
            while True:
                scoreRow = next(reader)
                raw, converted = str(int(scoreRow[0])), str(int(scoreRow[1]))
                rubric['buckets'][converted] = raw
        except ValueError:
            pass

        # Make the question:content rows into key-value pairs in the grading object
        try:
            questionRow = next(reader)
            while True:
                rubric['grading'][str(questionRow[0])] = questionRow[1].replace(' ', '').split(',')
                questionRow = next(reader)
        except StopIteration:
            pass

        # Writes the rubric to the appropriate file, asking the user before overwriting old rubrics
        with open(RUBRIC_FILE, 'r+') as file:
            try:
                saved_rubrics = json.loads(file.read())
            except ValueError as e:
                saved_rubrics = {}
            if rubric['data']['name'] not in saved_rubrics or input('This will overwrite an existing rubric. Continue? (y/n): ') == 'y':
                saved_rubrics[rubric['data']['name']] = rubric
                file.seek(0)
                file.write(json.dumps(saved_rubrics))
                file.truncate()
                print('Rubric saved.')
            else:
                print('Rubric not saved. If you wish to avoid an overwrite, change the "Name" value in the spreadsheet before converting it to .csv')

    except (IndexError, ValueError) as e:
        print(e, '\nThis was probably caused by bad formating before .csv conversion.')
        exit()
